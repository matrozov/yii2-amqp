<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

/**
 * Interface FindOneExecuteJob
 * @package matrozov\yii2amqp\jobs\model
 */
interface FindOneExecuteJob extends ModelExecuteJob
{
    public function executeFindOne();
}