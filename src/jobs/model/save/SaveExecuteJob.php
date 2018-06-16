<?php
namespace matrozov\yii2amqp\jobs\model\save;

/**
 * Interface SaveExecuteJob
 * @package matrozov\yii2amqp\jobs\model
 */
interface SaveExecuteJob
{
    public function executeSave();
}