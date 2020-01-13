<?php

namespace matrozov\yii2amqp\jobs\simple\autoBatch;

use Interop\Amqp\AmqpConsumer;
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
use Throwable;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\redis\Connection as RedisConnection;

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

    /** @var AmqpConsumer $_consumer */
    protected $_consumer;

    /** @var ExecuteJob[] $_jobs */
    protected $_jobs;

    const FAIL_DELAY_MULTIPLIER = 10;

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

        $classNameWON = array_pop($items);
        $classNamePrefix = substr(md5($jobClass), 0, 8);

        return $jobClass::exchangeName().'.auto.batch.'.$classNamePrefix.'.'.$classNameWON;
    }

    /**
     * @param Connection $connection
     * @param string     $name
     * @param string     $exchange
     * @param int        $ttl
     *
     * @return AmqpQueue
     */
    protected static function getQueue(Connection $connection, string $name, string $exchange, int $ttl): AmqpQueue
    {
        $queue = $connection->context->createQueue($name);
        $queue->addFlag(AmqpDestination::FLAG_DURABLE);
        $queue->setArgument('x-message-ttl', $ttl * self::FAIL_DELAY_MULTIPLIER * 1000);
        $queue->setArgument('x-dead-letter-exchange', $exchange);
        $queue->setArgument('x-dead-letter-routing-key', '');

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
        $producer = $connection->context->createProducer();

        $producer->send($queue, $newMessage);
    }

    /**
     * @param Connection $connection
     * @param string     $jobClass
     * @param bool       $immediate
     * @param bool       $finalize
     */
    protected static function sendTrigger(Connection $connection, string $jobClass, bool $immediate = false, bool $finalize = false)
    {
        /** @var AutoBatchExecuteJob $jobClass */
        $trigger = new static();
        $trigger->jobClass = $jobClass;
        $trigger->finalize = $finalize;
        $trigger->delay = $immediate ? null : $jobClass::autoBatchDelay();

        $connection->send($trigger, $jobClass::exchangeName());
    }

    /**
     * @param Connection          $connection
     * @param AmqpMessage         $message
     * @param AutoBatchExecuteJob $job
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     */
    public static function batchJob(Connection $connection, AmqpMessage $message, AutoBatchExecuteJob $job)
    {
        /** @var AutoBatchExecuteJob $jobClass */
        $jobClass = get_class($job);
        $name = self::name($jobClass);
        $atomic = $job::autoBatchAtomicProvider();

        $queue = self::getQueue($connection, $name, $jobClass::exchangeName(), $jobClass::autoBatchDelay());
        $connection->context->declareQueue($queue);

        self::sendToBatchQueue($connection, $queue, $message);

        $canTrigger = self::triggerSet($atomic, $name, $jobClass::autoBatchDelay());

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
     * @throws Throwable
     */
    public function execute(Connection $connection, AmqpMessage $message)
    {
        /** @var AutoBatchExecuteJob $jobClass */
        $jobClass = $this->jobClass;
        $name = self::name($jobClass);
        $atomic = $jobClass::autoBatchAtomicProvider();
        $batchCount = $jobClass::autoBatchCount();

        $queue = self::getQueue($connection, $name, $jobClass::exchangeName(), $jobClass::autoBatchDelay());
        $connection->context->declareQueue($queue);
        $this->_consumer = $connection->context->createConsumer($queue);

        $this->_jobs = [];

        if (!$this->finalize) {
            for ($i = 0; $i < $batchCount; $i++) {
                $item_msg = $this->_consumer->receiveNoWait();

                if (!$item_msg) {
                    break;
                }

                $this->_jobs[] = $connection->messageToJob($item_msg, $this->_consumer);
            }

            self::triggerUnset($atomic, $name);
        }

        $queueCount = $connection->context->declareQueue($queue);

        if ($queueCount > 0) {
            $canTrigger = self::triggerSet($atomic, $name, $jobClass::autoBatchDelay());

            if ($canTrigger) {
                self::sendTrigger($connection, $jobClass, $queueCount >= $batchCount);
            }
        }
        elseif (!$this->finalize) {
            self::sendTrigger($connection, $jobClass, false, true);
        }

        $countJobs = count($this->_jobs);

        if ($countJobs > 0) {
            try {
                $jobClass::executeAutoBatch($connection, $this->_jobs, $this);
            }
            catch (Throwable $e) {
                foreach ($this->_jobs as $job) {
                    $this->_consumer->reject($job->getMessage(), true);
                }

                $canTrigger = self::triggerSet($atomic, $name, $jobClass::autoBatchDelay());

                if (!$canTrigger) {
                    self::sendTrigger($connection, $jobClass, true);
                }

                throw $e;
            }

            foreach ($this->_jobs as $job) {
                $this->_consumer->acknowledge($job->getMessage());
            }
        }
    }

    /**
     * @param Connection           $connection
     * @param AutoBatchExecuteJob  $job
     * @param \Exception|Throwable $error
     *
     * @return bool
     * @throws ErrorException
     * @throws Throwable
     */
    public function redelivery(Connection $connection, AutoBatchExecuteJob $job, $error): bool
    {
        $index = array_search($job, $this->_jobs);

        if ($index === false) {
            throw new ErrorException('Unknown job!');
        }

        $exchange = $connection->getExchange($job::exchangeName());

        if (!$connection->redelivery($job, $job->getMessage(), $exchange, $error)) {
            return false;
        }

        $this->_consumer->acknowledge($job->getMessage());

        unset($this->_jobs[$index]);

        return true;
    }

    /**
     * @param mixed  $atomic
     * @param string $key
     * @param int    $ttl
     *
     * @return bool
     * @throws InvalidConfigException
     */
    protected static function triggerSet($atomic, string $key, int $ttl): bool
    {
        if ($atomic instanceof RedisConnection) {
            /** @var RedisConnection $atomic */
            if ($atomic->setnx($key, 1)) {
                $atomic->expire($key, $ttl * self::FAIL_DELAY_MULTIPLIER);

                return true;
            }

            return false;
        }
        elseif ($atomic instanceof Redis) {
            /** @var Redis $atomic */
            if ($atomic->setnx($key, 1)) {
                $atomic->expire($key, $ttl * self::FAIL_DELAY_MULTIPLIER);

                return true;
            }

            return false;
        }
        elseif ($atomic instanceof Memcache) {
            /** @var Memcache $atomic */
            return $atomic->add($key, 1, null, $ttl * self::FAIL_DELAY_MULTIPLIER);
        }
        elseif ($atomic instanceof Memcached) {
            /** @var Memcached $atomic */
            return $atomic->add($key, 1, $ttl * self::FAIL_DELAY_MULTIPLIER);
        }

        throw new InvalidConfigException('Unknown atomic provider type!');
    }

    /**
     * @param mixed  $atomic
     * @param string $key
     *
     * @throws InvalidConfigException
     */
    protected static function triggerUnset($atomic, string $key)
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
        else {
            throw new InvalidConfigException('Unknown atomic provider type!');
        }
    }
}
