<?php
namespace matrozov\yii2amqp;

use matrozov\yii2amqp\jobs\BaseJob;
use Interop\Amqp\AmqpMessage;

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