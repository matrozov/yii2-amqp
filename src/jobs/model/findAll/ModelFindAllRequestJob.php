<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Interface ModelFindAllRequestJob
 * @package matrozov\yii2amqp\jobs\model\findAll
 */
interface ModelFindAllRequestJob extends RpcRequestJob
{
    public static function findAll($conditions, $connection = null);
}