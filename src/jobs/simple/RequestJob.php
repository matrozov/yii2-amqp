<?php
namespace matrozov\yii2amqp\jobs\simple;

use matrozov\yii2amqp\jobs\BaseJob;

/**
 * Interface RequestJob
 * @package matrozov\yii2amqp\jobs
 */
interface RequestJob extends BaseJob
{
    public static function exchangeName();
}
