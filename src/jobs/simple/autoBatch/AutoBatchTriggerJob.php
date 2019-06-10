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
 */
class AutoBatchTriggerJob implements RequestJob, ExecuteJob, DelayedJob
{
    use RequestJobTrait;
    use ExecuteJobTrait;
    use DelayedJobTrait;

    public $jobClass;

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
     *
     * @throws ErrorException
     */
    protected static function sendTrigger(Connection $connection, string $jobClass, bool $immediate = false)
    {
        /** @var AutoBatchExecuteJob $jobClass */
        $trigger = new static();
        $trigger->jobClass = $jobClass;
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
        $name   = self::name(get_class($job));
        $queue  = self::getQueue($connection, $name);
        $atomic = $job::autoBatchAtomicProvider();

        $count = self::atomic($atomic, $name, 1);

        if ($count === false) {
            throw new ErrorException('Can\'t set atomic value!');
        }

        self::sendToBatchQueue($connection, $queue, $message);

        if ($count == 1) {
            self::sendTrigger($connection, get_class($job));
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

        for ($i = 0; $i < $batchCount; $i++) {
            $item_msg = $consumer->receiveNoWait();

            if (!$item_msg) {
                break;
            }

            $jobs[] = $connection->messageToJob($item_msg, $consumer);
            $msgs[] = $item_msg;
        }

        $countJobs = count($jobs);

        $count = self::atomic($atomic, $name, -$countJobs);

        if ($count === false) {
            throw new ErrorException('Can\'t set atomic value!');
        }

        if ($count > 0) {
            self::sendTrigger($connection, $jobClass, $count >= $batchCount);
        }

        if (count($jobs) > 0) {
            try {
                $jobClass::executeAutoBatch($connection, $jobs);
            }
            catch (\Exception $e) {
                foreach ($msgs as $msg) {
                    $consumer->reject($msg, true);
                }

                $count = self::atomic($atomic, $name, $countJobs);

                if ($count === false) {
                    throw new ErrorException('Can\'t set atomic value!');
                }

                if ($count == $countJobs) {
                    self::sendTrigger($connection, $jobClass);
                }

                throw $e;
            }

            foreach ($msgs as $msg) {
                $consumer->acknowledge($msg);
            }
        }
    }

    /**
     * @param RedisConnection|Redis|Memcache|Memcached $atomic
     * @param string                             $key
     * @param int                                $value
     *
     * @return false|int
     * @throws InvalidConfigException
     */
    protected static function atomic($atomic, $key, $value)
    {
        if ($atomic instanceof RedisConnection) {
            /** @var RedisConnection $atomic */

            if ($value > 0) {
                return $atomic->incrby($key, $value);
            }

            return $atomic->decrby($key, -$value);
        }
        elseif ($atomic instanceof Redis) {
            /** @var Redis $atomic */

            if ($value > 0) {
                return $atomic->incrby($key, $value);
            }

            return $atomic->decrby($key, -$value);
        }
        elseif ($atomic instanceof Memcache) {
            /** @var Memcache $atomic */

            if ($value > 0) {
                return $atomic->increment($key, $value) + $value;
            }

            return $atomic->decrement($key, -$value) - $value;
        }
        elseif ($atomic instanceof Memcached) {
            /** @var Memcached $atomic */

            if ($value > 0) {
                return $atomic->increment($key, $value) + $value;
            }

            return $atomic->decrement($key, -$value) - $value;
        }

        throw new InvalidConfigException('Unknown atomic provider type!');
    }
}
