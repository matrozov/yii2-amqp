<?php
namespace matrozov\yii2amqp\jobs;

/**
 * Interface BaseJob
 * @package matrozov\yii2amqp\jobs
 */
interface BaseJob
{
    public function deliveryMode();
}
