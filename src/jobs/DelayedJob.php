<?php

namespace matrozov\yii2amqp\jobs;

/**
 * Interface DelayedJob
 * @package matrozov\yii2amqp\jobs
 */
interface DelayedJob
{
    /**
     * @return float|int|null
     */
    public function getDelay();
}