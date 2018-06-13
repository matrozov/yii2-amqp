<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Interface ModelDeleteRequestJob
 * @package matrozov\yii2amqp\jobs\model\deleteAll
 */
interface ModelDeleteRequestJob extends RpcRequestJob
{
    /**
     * @return array
     */
    public function primaryKeys();

    public function delete(Connection $connection = null);
    public function deleteAll($conditions, Connection $connection = null);
}