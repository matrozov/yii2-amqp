<?php
namespace matrozov\yii2amqp\traits;

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
    public function deliveryMode()
    {
        return AmqpMessage::DELIVERY_MODE_PERSISTENT;
    }
}
