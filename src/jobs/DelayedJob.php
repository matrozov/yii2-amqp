<?php
namespace matrozov\yii2amqp\jobs;

/**
 * Interface DelayedJob
 * @package matrozov\yii2amqp\jobs
 */
interface DelayedJob
{
    /**
     * @param float|int|null $delay
     *
     * @return static
     */
    public function setDelay($delay = null);

    /**
     * @return float|int|null
     */
    public function getDelay();
}