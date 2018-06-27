<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

interface FindAllExecuteJob extends ModelExecuteJob
{
    public function executeFindAll();
}