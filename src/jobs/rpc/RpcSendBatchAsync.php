<?php

namespace matrozov\yii2amqp\jobs\rpc;

use Interop\Amqp\AmqpConsumer;
use Interop\Queue\Exception\DeliveryDelayNotSupportedException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\exceptions\NeedRedeliveryException;
use matrozov\yii2amqp\exceptions\RpcTimeoutException;
use Throwable;
use yii\base\ErrorException;
use yii\web\HttpException;

/**
 * Class RpcSendBatchAsync
 *
 * @package matrozov\yii2amqp
 */
class RpcSendBatchAsync
{
    /** @var Connection */
    protected $_connection;
    /** @var AmqpConsumer */
    protected $_callbackConsumer;
    /** @var array */
    protected $_linked;

    /** @var float */
    protected $_end;
    /** @var int */
    protected $_success;

    /**
     * RpcSendBatchAsync constructor.
     *
     * @param Connection     $connection
     * @param AmqpConsumer   $callbackConsumer
     * @param array          $linked
     * @param int|null|false $rpcTimeout
     */
    public function __construct(Connection $connection, AmqpConsumer $callbackConsumer, array $linked, $rpcTimeout)
    {
        $this->_connection       = $connection;
        $this->_callbackConsumer = $callbackConsumer;
        $this->_linked           = $linked;

        $rpcTimeout = ($rpcTimeout !== false) ? $rpcTimeout : $this->_connection->rpcTimeout;

        if ($rpcTimeout === null) {
            $this->_end = null;
        } else {
            $this->_end = microtime(true) + $rpcTimeout;
        }

        $this->_success = 0;
    }

    /**
     * @param int|null $timeout
     * @return bool
     * @throws ErrorException
     * @throws HttpException
     * @throws RpcTimeoutException
     * @throws DeliveryDelayNotSupportedException
     * @throws Throwable
     */
    public function isReady($timeout = null)
    {
        if ($timeout === null) {
            $end = $this->_end;
        } else {
            $end = microtime(true) + $timeout;

            if ($this->_end !== null) {
                $end = min($end, $this->_end);
            }
        }

        while ($this->_success < count($this->_linked)) {
            $responseMessage = $this->_callbackConsumer->receive((int)(($end - microtime(true)) * 1000));

            if (!$responseMessage) {
                if (($this->_end !== null) && (microtime(true) > $this->_end)) {
                    throw new RpcTimeoutException('Queue timeout!');
                }

                if (($end !== null) && (microtime(true) > $end)) {
                    break;
                }

                continue;
            }

            $correlationId = $responseMessage->getCorrelationId();

            if (!array_key_exists($correlationId, $this->_linked)) {
                $error = new NeedRedeliveryException('Invalid correlation ID');

                if ($this->_connection->redelivery(null, $responseMessage, $this->_callbackConsumer->getQueue(), $error)) {
                    $this->_callbackConsumer->acknowledge($responseMessage);
                } else {
                    throw new ErrorException('Can\'t redelivery invalid callback message');
                }

                if (($this->_end !== null) && (microtime(true) > $this->_end)) {
                    throw new RpcTimeoutException('Queue timeout!');
                }

                if (($end !== null) && (microtime(true) > $end)) {
                    break;
                }

                continue;
            }

            $this->_callbackConsumer->acknowledge($responseMessage);

            $this->_success++;

            $links[$correlationId]['response'] = $responseMessage;

            $responseJob = $this->_connection->serializer->deserialize($responseMessage->getBody());

            if (!($responseJob instanceof RpcResponseJob)) {
                throw new ErrorException('Root object must be RpcResponseJob!');
            }

            if ($responseJob instanceof RpcFalseResponseJob) {
                $this->_linked[$correlationId]['result'] = false;
                continue;
            }

            if ($responseJob instanceof RpcExceptionResponseJob) {
                throw $responseJob->exception();
            }

            $this->_linked[$correlationId]['result'] = $responseJob;
        }

        return $this->_success == count($this->_linked);
    }

    /**
     * @return false[]|RpcResponseJob[]
     * @throws DeliveryDelayNotSupportedException
     * @throws ErrorException
     * @throws HttpException
     * @throws RpcTimeoutException
     * @throws Throwable
     */
    public function result()
    {
        if (!$this->isReady()) {
            return [];
        }

        $result = [];

        foreach ($this->_linked as $idx => $link) {
            $result[$link['idx']] = $link['result'];
        }

        return $result;
    }
}