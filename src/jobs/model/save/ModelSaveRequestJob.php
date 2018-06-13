<?php
namespace matrozov\yii2amqp\jobs\model\save;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Interface ModelSaveRequestJob
 * @package matrozov\yii2amqp\jobs\model\save
 */
interface ModelSaveRequestJob extends RpcRequestJob
{
    public function toArray();
    public function validate();
    public function clearErrors($attribute = null);
    public function addErrors(array $items);

    public function save(Connection $connection = null);
}