<?php

namespace matrozov\yii2amqp\events;

use Interop\Amqp\AmqpDestination;

/**
 * Class SendEvent
 * @package matrozov\yii2amqp\events
 *
 * @property AmqpDestination $target
 */
class SendEvent extends Event
{
    /**
     * @var AmqpDestination
     */
    public $target;
}
