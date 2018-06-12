<?php
namespace matrozov\yii2amqp\jobs\simple;

use Interop\Amqp\AmqpMessage;

/**
 * Trait BaseJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait BaseJobTrait
{
    /**
     * @return int
     */
    public static function deliveryMode()
    {
        return AmqpMessage::DELIVERY_MODE_PERSISTENT;
    }
}
