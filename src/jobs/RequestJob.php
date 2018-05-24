<?php
namespace matrozov\yii2amqp\jobs;

/**
 * Interface RequestJob
 * @package matrozov\yii2amqp\jobs
 */
interface RequestJob extends BaseJob
{
    public function exchangeName();
}
