<?php

namespace matrozov\yii2amqp\events;

use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;

/**
 * Class ExecuteEvent
 * @package matrozov\yii2amqp\events
 *
 * @property RpcResponseJob|null $responseJob
 * @property AmqpMessage|null    $responseMessage
 * @property AmqpConsumer        $consumer
 */
class ExecuteEvent extends Event
{
    /**
     * @var RpcResponseJob|null
     */
    public $responseJob;

    /**
     * @var AmqpMessage|null
     */
    public $responseMessage;

    /**
     * @var AmqpConsumer
     */
    public $consumer;
}