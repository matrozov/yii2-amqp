<?php
namespace matrozov\yii2amqp\jobs\searchModel;

use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Interface SearchModelRequestJob
 * @package matrozov\yii2amqp\jobs
 */
interface SearchModelRequestJob extends RpcRequestJob
{
    public function clearErrors($attribute = null);

    public function addErrors(array $items);
}