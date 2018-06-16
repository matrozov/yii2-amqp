<?php
namespace matrozov\yii2amqp\jobs\model\delete;

/**
 * Interface DeleteExecuteJob
 * @package matrozov\yii2amqp\jobs\model
 */
interface DeleteExecuteJob
{
    public function executeDelete();
}