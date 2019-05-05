<?php

namespace matrozov\yii2amqp\jobs;

/**
 * Trait DelayedJobTrait
 * @package matrozov\yii2amqp\jobs
 *
 * @property float|int|null $delay
 */
trait DelayedJobTrait
{
    /**
     * @var float|int|null $delay
     */
    private $delay;

    /**
     * @param float|int|null $delay
     *
     * @return static
     */
    public function setDelay($delay = null)
    {
        $this->delay = $delay;

        return $this;
    }

    /**
     * @return float|int|null
     */
    public function getDelay()
    {
        return $this->delay;
    }
}