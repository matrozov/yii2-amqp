<?php

namespace matrozov\yii2amqp\jobs\simple\autoBatch;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\simple\ExecuteJob;
use matrozov\yii2amqp\jobs\simple\RequestJob;
use yii\redis\Connection as RedisConnection;
use Redis;
use Memcache;
use Memcached;

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
     * @return RedisConnection|Redis|Memcache|Memcached
     */
    public static function autoBatchAtomicProvider();

    /**
     * @param Connection          $connection
     * @param array               $items
     * @param AutoBatchTriggerJob $trigger
     */
    public static function executeAutoBatch(Connection $connection, array $items, AutoBatchTriggerJob $trigger);
}
