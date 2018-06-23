<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

/**
 * Interface FindAllExecuteJob
 * @package matrozov\yii2amqp\jobs\model
 */
interface FindAllExecuteJob extends ModelExecuteJob
{
    public function executeFindAll();
}