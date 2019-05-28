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
     * @throws Exception
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @throws ErrorException
     */
    public static function batchJob(Connection $connection, AmqpMessage $message, AutoBatchExecuteJob $job)
    {
        $name  = self::name(get_class($job));
        $queue = self::getQueue($connection, $name);

        $mutex   = $job::autoBatchMutex();
        $inQueue = $connection->context->declareQueue($queue);

        if ($inQueue == 0) {
            // Add to batch queue and start trigger job (if needed)

            if (!$mutex->acquire($name, 10)) {
                throw new ErrorException('Can\'t acquire mutex');
            }

            $inQueue = $connection->context->declareQueue($queue);

            if ($inQueue == 0) {
                self::sendToBatchQueue($connection, $queue, $message);

                if (!$mutex->release($name)) {
                    throw new ErrorException('Can\'t release mutex');
                }

                self::sendTrigger($connection, get_class($job));
            }
            else {
                self::sendToBatchQueue($connection, $queue, $message);

                if (!$mutex->release($name)) {
                    throw new ErrorException('Can\'t release mutex');
                }
            }
        }
        else {
            // Simple add to batch queue

            self::sendToBatchQueue($connection, $queue, $message);
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

        $mutex      = $jobClass::autoBatchMutex();
        $batchCount = $jobClass::autoBatchCount();

        if (!$mutex->acquire($name)) {
            throw new ErrorException('Can\'t acquire mutex');
        }

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

        $inQueue = $connection->context->declareQueue($queue);

        if ($inQueue > 0) {
            self::sendTrigger($connection, $jobClass, $inQueue >= $batchCount);
        }

        if (!$mutex->release($name)) {
            throw new ErrorException('Can\'t release mutex');
        }

        if (count($jobs) > 0) {
            try {
                $jobClass::executeAutoBatch($connection, $jobs);
            }
            catch (\Exception $e) {
                foreach ($msgs as $msg) {
                    $consumer->reject($msg, true);
                }

                throw $e;
            }

            foreach ($msgs as $msg) {
                $consumer->acknowledge($msg);
            }
        }
    }
}
