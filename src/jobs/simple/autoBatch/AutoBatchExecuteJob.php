<?php

namespace matrozov\yii2amqp\jobs\simple\autoBatch;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\simple\ExecuteJob;
use matrozov\yii2amqp\jobs\simple\RequestJob;
use yii\mutex\Mutex;

/**
 * Interface AutoBatchExecuteJob
 * @package matrozov\yii2amqp\jobs\simple\autoBatch
 */
interface AutoBatchExecuteJob extends ExecuteJob, RequestJob
{
    /**
     * Specify auto batch timeout
     *
     * @return int
     */
    public static function autoBatchDelay(): int;

    /**
     * Specify auto batch count
     *
     * @return int
     */
    public static function autoBatchCount(): int;

    /**
     * @return Mutex
     */
    public static function autoBatchMutex(): Mutex;

    /**
     * @param Connection  $connection
     * @param array       $items
     */
    public static function executeAutoBatch(Connection $connection, array $items);
}
