<?php
namespace matrozov\yii2amqp\events;

use matrozov\yii2amqp\Event;
use Interop\Queue\PsrDestination;

/**
 * Class SendEvent
 * @package matrozov\yii2amqp\events
 *
 * @property PsrDestination $target
 */
class SendEvent extends Event
{
    /**
     * @var PsrDestination
     */
    public $target;
}