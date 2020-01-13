<?php

namespace matrozov\yii2amqp\jobs\simple\autoBatch;

use Interop\Amqp\AmqpMessage;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use matrozov\yii2amqp\Connection;
use yii\base\InvalidConfigException;

/**
 * Trait AutoBatchExecuteJobTrait
 * @package matrozov\yii2amqp\jobs\simple\autoBatch
 */
trait AutoBatchExecuteJobTrait
{
    /**
     * @param Connection  $connection
     * @param AmqpMessage $message
     *
     * @throws Exception
     * @throws InvalidConfigException
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     */
    public function execute(Connection $connection, AmqpMessage $message)
    {
        AutoBatchTriggerJob::batchJob($connection, $message, $this);
    }
}
