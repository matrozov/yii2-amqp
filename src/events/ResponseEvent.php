<?php

namespace matrozov\yii2amqp\events;

use Interop\Amqp\AmqpConsumer;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;
use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;

/**
 * Class ResponseEvent
 * @package matrozov\yii2amqp\events
 */
class ResponseEvent extends ExecuteEvent
{
}