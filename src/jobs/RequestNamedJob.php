<?php
namespace matrozov\yii2amqp\jobs;

/**
 * Interface RequestNamedJob
 * @package matrozov\yii2amqp\jobs
 */
interface RequestNamedJob
{
    public static function jobName();
}