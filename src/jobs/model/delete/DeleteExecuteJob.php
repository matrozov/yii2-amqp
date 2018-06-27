<?php
namespace matrozov\yii2amqp\jobs\model\delete;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

interface DeleteExecuteJob extends ModelExecuteJob
{
    public function executeDelete();
}