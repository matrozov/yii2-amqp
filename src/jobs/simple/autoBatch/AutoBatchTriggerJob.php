<?php

namespace matrozov\yii2amqp\jobs\simple\autoBatch;

use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\DelayedJob;
use matrozov\yii2amqp\jobs\DelayedJobTrait;
use matrozov\yii2amqp\jobs\simple\ExecuteJob;
use matrozov\yii2amqp\jobs\simple\ExecuteJobTrait;
use matrozov\yii2amqp\jobs\simple\RequestJob;
use matrozov\yii2amqp\jobs\simple\RequestJobTrait;
use Memcache;
use Memcached;
use Redis;
use yii\base\InvalidConfigException;
use yii\redis\Connection as RedisConnection;
use yii\base\ErrorException;

/**
 * Class AutoBatchTriggerJob
 * @package matrozov\yii2amqp\jobs\simple\autoBatch
 *
 * @property string $jobClass
 * @property bool   $finalize
 */
class AutoBatchTriggerJob implements RequestJob, ExecuteJob, DelayedJob
{
    use RequestJobTrait;
    use ExecuteJobTrait;
    use DelayedJobTrait;

    public $jobClass;

    public $finalize;

    /**
     * @return string
     */
    public static function exchangeName(): string
    {
        return '';
    }

    /**
     * @param string $jobClass
     *
     * @return string
     */
    protected static function name(string $jobClass): string
    {
        /** @var AutoBatchExecuteJob $jobClass */

        $items = explode('\\', $jobClass);

        $classNameWON    = array_pop($items);
        $classNamePrefix = substr(md5($jobClass), 0, 8);

        return $jobClass::exchangeName() . '.auto.batch.' . $classNamePrefix . '.' . $classNameWON;
    }

    /**
     * @param Connection $connection
     * @param string     $name
     *
     * @return AmqpQueue
     */
    protected static function getQueue(Connection $connection, string $name): AmqpQueue
    {
        $queue = $connection->context->createQueue($name);
        $queue->addFlag(AmqpDestination::FLAG_IFUNUSED);
        $queue->addFlag(AmqpDestination::FLAG_AUTODELETE);
        $queue->addFlag(AmqpDestination::FLAG_DURABLE);

        return $queue;
    }

    /**
     * @param Connection  $connection
     * @param AmqpQueue   $queue
     * @param AmqpMessage $message
     *
     * @throws Exception
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     */
    protected static function sendToBatchQueue(Connection $connection, AmqpQueue $queue, AmqpMessage $message)
    {
        $newMessage = $connection->context->createMessage($message->getBody(), $message->getProperties(), $message->getHeaders());
        $producer   = $connection->context->createProducer();

        $producer->send($queue, $newMessage);
    }

    /**
     * @param Connection $connection
     * @param string     $jobClass
     * @param bool       $immediate
     * @param bool       $finalize
     *
     * @throws ErrorException
     */
    protected static function sendTrigger(Connection $connection, string $jobClass, bool $immediate = false, bool $finalize = false)
    {
        /** @var AutoBatchExecuteJob $jobClass */
        $trigger = new static();
        $trigger->jobClass = $jobClass;
        $trigger->finalize = $finalize;
        $trigger->delay    = $immediate ? null : $jobClass::autoBatchDelay();

        $connection->send($trigger, $jobClass::exchangeName());
    }

    /**
     * @param Connection          $connection
     * @param AmqpMessage         $message
     * @param AutoBatchExecuteJob $job
     *
     * @throws ErrorException
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     */
    public static function batchJob(Connection $connection, AmqpMessage $message, AutoBatchExecuteJob $job)
    {
        /** @var AutoBatchExecuteJob $jobClass */
        $jobClass = get_class($job);
        $name     = self::name($jobClass);
        $atomic   = $job::autoBatchAtomicProvider();

        $queue    = self::getQueue($connection, $name);
        $connection->context->declareQueue($queue);

        self::sendToBatchQueue($connection, $queue, $message);

        $canTrigger = self::triggerSet($atomic, $name);

        if ($canTrigger) {
            self::sendTrigger($connection, $jobClass);
        }
    }

    /**
     * @param Connection  $connection
     * @param AmqpMessage $message
     *
     * @throws ErrorException
     * @throws Exception
     * @throws \Exception
     */
    public function execute(Connection $connection, AmqpMessage $message)
    {
        /** @var AutoBatchExecuteJob $jobClass */
        $jobClass   = $this->jobClass;
        $name       = self::name($jobClass);
        $atomic     = $jobClass::autoBatchAtomicProvider();
        $batchCount = $jobClass::autoBatchCount();

        $queue = self::getQueue($connection, $name);
        $connection->context->declareQueue($queue);
        $consumer = $connection->context->createConsumer($queue);

        $jobs = [];
        $msgs = [];

        if (!$this->finalize) {
            for ($i = 0; $i < $batchCount; $i++) {
                $item_msg = $consumer->receiveNoWait();

                if (!$item_msg) {
                    break;
                }

                $jobs[] = $connection->messageToJob($item_msg, $consumer);
                $msgs[] = $item_msg;
            }
        }

        $queueCount = $connection->context->declareQueue($queue);

        if ($queueCount > 0) {
            $canTrigger = self::triggerSet($atomic, $name);

            if ($canTrigger) {
                self::sendTrigger($connection, $jobClass, $queueCount >= $batchCount);
            }
        }
        elseif (!$this->finalize) {
            self::sendTrigger($connection, $jobClass, false, true);
        }

        $countJobs = count($jobs);

        if ($countJobs > 0) {
            try {
                $jobClass::executeAutoBatch($connection, $jobs);
            }
            catch (\Exception $e) {
                foreach ($msgs as $msg) {
                    $consumer->reject($msg, true);
                }

                $canTrigger = self::triggerSet($atomic, $name);

                if (!$canTrigger) {
                    self::sendTrigger($connection, $jobClass, true);
                }

                throw $e;
            }

            foreach ($msgs as $msg) {
                $consumer->acknowledge($msg);
            }
        }
    }

    /**
     * @param mixed  $atomic
     * @param string $key
     *
     * @return bool
     * @throws InvalidConfigException
     */
    protected static function triggerSet($atomic, string $key): bool
    {
        if ($atomic instanceof RedisConnection) {
            /** @var RedisConnection $atomic */
            return $atomic->setnx($key, 1);
        }
        elseif ($atomic instanceof Redis) {
            /** @var Redis $atomic */
            return $atomic->setnx($key, 1);
        }
        elseif ($atomic instanceof Memcache) {
            /** @var Memcache $atomic */
            return $atomic->add($key, 1);
        }
        elseif ($atomic instanceof Memcached) {
            /** @var Memcached $atomic */
            return $atomic->add($key, 1);
        }

        throw new InvalidConfigException('Unknown atomic provider type!');
    }

    /**
     * @param mixed  $atomic
     * @param string $key
     *
     * @throws InvalidConfigException
     */
    protected static function triggerUnSet($atomic, string $key)
    {
        if ($atomic instanceof RedisConnection) {
            /** @var RedisConnection $atomic */
            $atomic->del($key);
        }
        elseif ($atomic instanceof Redis) {
            /** @var Redis $atomic */
            $atomic->del($key);
        }
        elseif ($atomic instanceof Memcache) {
            /** @var Memcache $atomic */
            $atomic->delete($key);
        }
        elseif ($atomic instanceof Memcached) {
            /** @var Memcached $atomic */
            $atomic->delete($key);
        }

        throw new InvalidConfigException('Unknown atomic provider type!');
    }
}
