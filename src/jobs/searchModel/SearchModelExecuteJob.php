<?php
namespace matrozov\yii2amqp\jobs\searchModel;

use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;

/**
 * Interface SearchModelExecuteJob
 * @package matrozov\yii2amqp\jobs
 */
interface SearchModelExecuteJob extends RpcExecuteJob
{
    public function validate();
    public function search();

    public function getErrors();
}