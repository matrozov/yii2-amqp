<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

interface FindOneExecuteJob extends ModelExecuteJob
{
    const EXECUTE_METHOD = 'executeFindAll';

    public function executeFindAll();
}