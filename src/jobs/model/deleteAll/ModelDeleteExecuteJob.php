<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;

/**
 * Interface ModelDeleteExecuteJob
 * @package matrozov\yii2amqp\jobs\model\deleteAll
 */
interface ModelDeleteExecuteJob extends RpcExecuteJob
{
    public static function executeDeleteAll($conditions);
}