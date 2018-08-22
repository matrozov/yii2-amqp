<?php
namespace matrozov\yii2amqp\jobs;

/**
 * Interface PriorityJob
 * @package matrozov\yii2amqp\jobs
 */
interface PriorityJob
{
    /**
     * @param int|null $priority
     *
     * @return static
     */
    public function setPriority($priority = null);

    /**
     * @return int|null
     */
    public function getPriority();
}