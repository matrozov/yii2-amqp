<?php
namespace matrozov\yii2amqp\jobs\model\save;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

/**
 * Interface SaveModelExecuteJob
 * @package matrozov\yii2amqp\jobs\model\save
 */
interface SaveModelExecuteJob extends ModelExecuteJob
{
    public function executeSave(Connection $connection, AmqpMessage $message);
}