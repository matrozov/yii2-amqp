<?php
namespace matrozov\yii2amqp\jobs\simple;

use matrozov\yii2amqp\jobs\BaseJob;

/**
 * Interface RequestJob
 * @package matrozov\yii2amqp\jobs
 */
interface RequestJob extends BaseJob
{
    const EVENT_BEFORE_SEND = 'beforeSend';
    const EVENT_AFTER_SEND  = 'afterSend';

    public static function exchangeName();
}
