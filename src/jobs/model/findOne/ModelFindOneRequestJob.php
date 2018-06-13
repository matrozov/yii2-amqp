<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Interface ModelFindOneRequestJob
 * @package matrozov\yii2amqp\jobs\model\findOne
 */
interface ModelFindOneRequestJob extends RpcRequestJob
{
    public static function findOne($conditions, Connection $connection = null);
}