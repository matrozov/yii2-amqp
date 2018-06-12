<?php
namespace matrozov\yii2amqp\jobs\model\save;

use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;

/**
 * Interface ModelSaveExecuteJob
 * @package matrozov\yii2amqp\jobs
 */
interface ModelSaveExecuteJob extends RpcExecuteJob
{
    public function validate();
    public function getErrors();

    public function executeSave();
}