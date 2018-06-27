<?php
namespace matrozov\yii2amqp\jobs\model\delete;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

interface DeleteExecuteJob extends ModelExecuteJob
{
    const EXECUTE_METHOD = 'executeDelete';

    public function executeDelete();
}