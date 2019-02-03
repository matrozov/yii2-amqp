<?php

namespace matrozov\yii2amqp\jobs\model\findOne;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

/**
 * Interface FindOneModelExecuteJob
 * @package matrozov\yii2amqp\jobs\model\findOne
 */
interface FindOneModelExecuteJob extends ModelExecuteJob
{
    public function executeFindOne(Connection $connection, AmqpMessage $message);
}