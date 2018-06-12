<?php
namespace matrozov\yii2amqp\jobs\model\search;

use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;

/**
 * Interface ModelSearchExecuteJob
 * @package matrozov\yii2amqp\jobs
 */
interface ModelSearchExecuteJob extends RpcExecuteJob
{
    public function validate();
    public function search();

    public function getErrors();
}