<?php
namespace matrozov\yii2amqp\jobs\model\get;

use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;

/**
 * Interface ModelGetExecuteJob
 * @package matrozov\yii2amqp\jobs\model\find
 */
interface ModelGetExecuteJob extends RpcExecuteJob
{
    public static function executeFindOne($conditions);
}