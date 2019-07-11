<?php

namespace matrozov\yii2amqp\jobs\simple;

use Interop\Amqp\AmqpMessage;

/**
 * Trait ExecuteJobTrait
 * @package matrozov\yii2amqp\jobs\simple
 */
trait ExecuteJobTrait
{
    protected $_message;

    public function getMessage(): AmqpMessage
    {
        return $this->_message;
    }

    public function setMessage(AmqpMessage $message)
    {
        $this->_message = $message;
    }
}
