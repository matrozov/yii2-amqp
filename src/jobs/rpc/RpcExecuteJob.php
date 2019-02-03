<?php

namespace matrozov\yii2amqp\jobs\rpc;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\simple\ExecuteJob;

/**
 * Interface RpcExecuteJob
 * @package matrozov\yii2amqp\jobs\rpc
 */
interface RpcExecuteJob extends ExecuteJob
{
    public function executeRpc(Connection $connection, AmqpMessage $message);
}