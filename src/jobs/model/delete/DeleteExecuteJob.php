<?php
namespace matrozov\yii2amqp\jobs\model\delete;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

/**
 * Interface DeleteExecuteJob
 * @package matrozov\yii2amqp\jobs\model
 */
interface DeleteExecuteJob extends ModelExecuteJob
{
    public function executeDelete();
}