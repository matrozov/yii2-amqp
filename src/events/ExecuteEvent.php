<?php
namespace matrozov\yii2amqp\events;

use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;
use Interop\Amqp\AmqpConsumer;

/**
 * Class ExecuteEvent
 * @package matrozov\yii2amqp\events
 *
 * @property RpcResponseJob $responseJob
 * @property AmqpConsumer   $consumer
 */
class ExecuteEvent extends Event
{
    /**
     * @var RpcResponseJob
     */
    public $responseJob;

    /**
     * @var AmqpConsumer
     */
    public $consumer;
}