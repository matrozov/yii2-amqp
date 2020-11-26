<?php

namespace matrozov\yii2amqp\events;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\jobs\BaseJob;

/**
 * Class Event
 * @package matrozov\yii2amqp
 *
 * @property BaseJob|null $requestJob
 * @property AmqpMessage  $requestMessage
 *
 * @property AmqpMessage  $message // Backward compatibility
 */
class Event extends \yii\base\Event
{
    /**
     * @var BaseJob|null
     */
    public $requestJob;

    /**
     * @var AmqpMessage
     */
    public $requestMessage;

    /**
     * @return AmqpMessage
     */
    public function getMessage()
    {
        return $this->requestMessage;
    }
}
