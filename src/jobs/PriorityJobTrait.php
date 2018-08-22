<?php
namespace matrozov\yii2amqp\jobs;

/**
 * Trait PriorityJobTrait
 * @package matrozov\yii2amqp\jobs
 */
trait PriorityJobTrait
{
    /**
     * @var int|null $priority
     */
    private $priority;

    /**
     * @param int|null $priority
     *
     * @return static
     */
    public function setPriority($priority = null)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getPriority()
    {
        return $this->priority;
    }
}