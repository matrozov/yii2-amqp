<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

interface DeleteAllExecuteJob extends ModelExecuteJob
{
    public function executeDeleteAll();
}