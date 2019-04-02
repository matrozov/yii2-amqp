<?php

namespace matrozov\yii2amqp;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Queue\Exception\InvalidDestinationException;
use Yii;

/**
 * Class DelayStrategy
 * @package matrozov\yii2amqp
 *
 * @property Connection $connection
 */
class DelayStrategy implements \Enqueue\AmqpTools\DelayStrategy
{
    /* @var Connection $connection */
    public $connection;

    /**
     * DelayStrategy constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     * @throws
     */
    public function delayMessage(AmqpContext $context, AmqpDestination $dest, AmqpMessage $message, int $delay): void
    {
        $properties = $message->getProperties();

        // The x-death header must be removed because of the bug in RabbitMQ.
        // It was reported that the bug is fixed since 3.5.4 but I tried with 3.6.1 and the bug still there.
        // https://github.com/rabbitmq/rabbitmq-server/issues/216
        unset($properties['x-death']);

        $delayMessage = $context->createMessage($message->getBody(), $properties, $message->getHeaders());
        $delayMessage->setRoutingKey($message->getRoutingKey());

        if ($dest instanceof AmqpTopic) {
            $routingKey = $message->getRoutingKey() ? '.' . $message->getRoutingKey() : '';

            $name = sprintf('%s%s.topic.delay.%s', $dest->getTopicName(), $routingKey, $delay);

            $delayQueue = $context->createQueue($name);
            $delayQueue->addFlag(AmqpTopic::FLAG_DURABLE);
            $delayQueue->setArgument('x-message-ttl', $delay);
            $delayQueue->setArgument('x-dead-letter-exchange', $dest->getTopicName());
            $delayQueue->setArgument('x-dead-letter-routing-key', (string) $delayMessage->getRoutingKey());
        } elseif ($dest instanceof AmqpQueue) {
            $name = sprintf('%s.queue.delay.%s', $dest->getQueueName(), $delay);

            $delayQueue = $context->createQueue($name);
            $delayQueue->addFlag(AmqpTopic::FLAG_DURABLE);
            $delayQueue->setArgument('x-message-ttl', $delay);
            $delayQueue->setArgument('x-dead-letter-exchange', '');
            $delayQueue->setArgument('x-dead-letter-routing-key', $dest->getQueueName());
        } else {
            throw new InvalidDestinationException(sprintf('The destination must be an instance of %s but got %s.',
                AmqpTopic::class.'|'.AmqpQueue::class,
                get_class($dest)
            ));
        }

        $context->declareQueue($delayQueue);

        $debug = [
            'app_id'     => Yii::$app->id,
            'request_id' => $this->connection->debugRequestId,
            'message_id' => $message->getMessageId(),
            'send_in'    => 'delay',
        ];

        try {
            $this->connection->sendMessage($delayQueue, null, $delayMessage);
        }
        catch (\Exception $exception) {
            if ($this->connection->debugger) {
                $debug['time']      = microtime(true);
                $debug['exception'] = $exception->getMessage();

                $this->connection->debug('send_end', $debug);
            }

            throw $exception;
        }

        if ($this->connection->debugger) {
            $debug['time'] = microtime(true);

            $this->connection->debug('send_end', $debug);
        }
    }
}
