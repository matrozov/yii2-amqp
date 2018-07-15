<?php
namespace matrozov\yii2amqp\jobs\model;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;

/**
 * Interface ModelExecuteJob
 * @package matrozov\yii2amqp\jobs\model
 */
interface ModelExecuteJob extends RpcExecuteJob
{
    public function validate();
    public function hasErrors();
    public function getErrors();

    public function executeModel(Connection $connection, AmqpMessage $message);
}