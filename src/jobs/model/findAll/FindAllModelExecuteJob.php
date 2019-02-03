<?php

namespace matrozov\yii2amqp\jobs\model\findAll;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

/**
 * Interface FindAllModelExecuteJob
 * @package matrozov\yii2amqp\jobs\model\findAll
 */
interface FindAllModelExecuteJob extends ModelExecuteJob
{
    public function executeFindAll(Connection $connection, AmqpMessage $message);
}