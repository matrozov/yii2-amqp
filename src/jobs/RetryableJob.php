<?php

namespace matrozov\yii2amqp\jobs;

use Exception;
use Throwable;

/**
 * Interface RetryableJob
 * @package matrozov\yii2amqp\jobs
 */
interface RetryableJob
{
    /**
     * @param int                 $attempt
     * @param Exception|Throwable $error
     *
     * @return bool
     */
    public function canRetry($attempt, $error);
}
