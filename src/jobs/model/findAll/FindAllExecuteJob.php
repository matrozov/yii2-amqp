<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

interface FindAllExecuteJob extends ModelExecuteJob
{
    const EXECUTE_METHOD = 'executeFindAll';

    public function executeFindAll();
}