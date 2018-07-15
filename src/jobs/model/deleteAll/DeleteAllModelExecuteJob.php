<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

/**
 * Interface DeleteAllModelExecuteJob
 * @package matrozov\yii2amqp\jobs\model\deleteAll
 */
interface DeleteAllModelExecuteJob extends ModelExecuteJob
{
    public function executeDeleteAll(Connection $connection, AmqpMessage $message);
}