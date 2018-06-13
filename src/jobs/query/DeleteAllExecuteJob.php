<?php
namespace matrozov\yii2amqp\jobs\query;

/**
 * Interface DeleteAllExecuteJob
 * @package matrozov\yii2amqp\jobs\query
 */
interface DeleteAllExecuteJob
{
    public function deleteAll();
}