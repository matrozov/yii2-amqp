<?php

namespace matrozov\yii2amqp\jobs\model\delete;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

/**
 * Interface DeleteModelExecuteJob
 * @package matrozov\yii2amqp\jobs\model\delete
 */
interface DeleteModelExecuteJob extends ModelExecuteJob
{
    public function executeDelete(Connection $connection, AmqpMessage $message);
}