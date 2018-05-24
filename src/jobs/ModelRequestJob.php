<?php
namespace matrozov\yii2amqp\jobs;

/**
 * Interface ModelRequestJob
 * @package matrozov\yii2amqp\jobs
 */
interface ModelRequestJob extends RpcRequestJob
{
    public function clearErrors($attribute = null);

    public function addErrors(array $items);
}