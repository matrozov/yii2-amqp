<?php
namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Interface ModelRequestJob
 * @package matrozov\yii2amqp\jobs\model
 */
interface ModelRequestJob extends RpcRequestJob
{
    public function validate();
    public function getScenario();
    public function toArray();

    public function clearErrors();
    public function addErrors(array $items);
    public function setAttributes($values, $safeOnly = true);
}