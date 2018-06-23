<?php
namespace matrozov\yii2amqp\jobs\model\save;

use matrozov\yii2amqp\jobs\model\ModelExecuteJob;

/**
 * Interface SaveExecuteJob
 * @package matrozov\yii2amqp\jobs\model
 */
interface SaveExecuteJob extends ModelExecuteJob
{
    public function executeSave();
}