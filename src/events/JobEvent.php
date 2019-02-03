<?php

namespace matrozov\yii2amqp\events;

use matrozov\yii2amqp\jobs\BaseJob;

/**
 * Class JobEvent
 * @package matrozov\yii2amqp\events
 *
 * @property BaseJob $job
 */
class JobEvent extends \yii\base\Event
{
    /**
     * @var BaseJob
     */
    public $job;
}