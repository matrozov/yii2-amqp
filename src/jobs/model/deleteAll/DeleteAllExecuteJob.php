<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

/**
 * Interface DeleteAllExecuteJob
 * @package matrozov\yii2amqp\jobs\model
 */
interface DeleteAllExecuteJob extends ModelExecuteJob
{
    public function executeDeleteAll();
}