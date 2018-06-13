<?php
namespace matrozov\yii2amqp\jobs\query;

/**
 * Interface FindOneExecuteJob
 * @package matrozov\yii2amqp\jobs\query
 */
interface FindOneExecuteJob
{
    public function findOne();
}