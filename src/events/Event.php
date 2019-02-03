<?php

namespace matrozov\yii2amqp\events;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\jobs\BaseJob;

/**
 * Class Event
 * @package matrozov\yii2amqp
 *
 * @property BaseJob     $requestJob
 * @property AmqpMessage $message
 */
class Event extends \yii\base\Event
{
    /**
     * @var BaseJob
     */
    public $requestJob;

    /**
     * @var AmqpMessage
     */
    public $message;
}