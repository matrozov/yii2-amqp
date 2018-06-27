<?php
namespace matrozov\yii2amqp\jobs\model\save;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

interface SaveExecuteJob extends ModelExecuteJob
{
    public function executeSave();
}