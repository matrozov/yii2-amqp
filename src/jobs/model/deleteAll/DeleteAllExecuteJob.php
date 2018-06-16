<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

/**
 * Interface DeleteAllExecuteJob
 * @package matrozov\yii2amqp\jobs\model
 */
interface DeleteAllExecuteJob
{
    public function executeDeleteAll();
}