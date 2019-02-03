<?php

namespace matrozov\yii2amqp\jobs\simple;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\BaseJob;

/**
 * Interface ExecuteJob
 * @package matrozov\yii2amqp\jobs\simple
 */
interface ExecuteJob extends BaseJob
{
    public function execute(Connection $connection, AmqpMessage $message);
}