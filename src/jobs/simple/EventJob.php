<?php
namespace matrozov\yii2amqp\jobs\simple;

use yii\base\Event;
use matrozov\yii2amqp\jobs\BaseJob;

/**
 * Class EventJob
 * @package matrozov\yii2amqp\jobs\simple
 *
 * @property BaseJob $job
 */
class EventJob extends Event
{
    /**
     * @var BaseJob
     */
    public $job;
}