<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;

/**
 * Interface ModelFindAllExecuteJob
 * @package matrozov\yii2amqp\jobs\model\findAll
 */
interface ModelFindAllExecuteJob extends RpcExecuteJob
{
    public static function executeFindAll($conditions);
}