<?php
namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Interface ModelRequestJob
 * @package matrozov\yii2amqp\jobs
 */
interface ModelRequestJob extends RpcRequestJob
{
    public function validate();

    public function clearErrors($attribute = null);
    public function addErrors(array $items);
}