<?php

namespace matrozov\yii2amqp\jobs;

/**
 * Interface ExpiredJob
 * @package matrozov\yii2amqp\jobs
 */
interface ExpiredJob
{
    /**
     * @return float|int|null
     */
    public function getTtl();
}