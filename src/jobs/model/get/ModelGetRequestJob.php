<?php
namespace matrozov\yii2amqp\jobs\model\get;

use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Interface ModelGetRequestJob
 * @package matrozov\yii2amqp\jobs
 */
interface ModelGetRequestJob extends RpcRequestJob
{
    public static function findOne($conditions);
}