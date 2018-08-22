<?php
namespace matrozov\yii2amqp\jobs;

/**
 * Interface PriorityJob
 * @package matrozov\yii2amqp\jobs
 */
interface PriorityJob
{
    /**
     * @return int|null
     */
    public function getPriority();
}