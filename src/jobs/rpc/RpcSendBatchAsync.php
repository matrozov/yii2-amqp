<?php

namespace matrozov\yii2amqp\jobs\rpc;

use Interop\Amqp\AmqpConsumer;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\exceptions\RpcTimeoutException;
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

    public function __construct($connection, $callbackConsumer, $linked)
    {
        $this->_connection       = $connection;
        $this->_callbackConsumer = $callbackConsumer;
        $this->_linked           = $linked;

        if ($this->_connection->rpcTimeout === null) {
            $this->_end = null;
        } else {
            $this->_end = microtime(true) + $this->_connection->rpcTimeout;
        }

        $this->_success = 0;
    }

    /**
     * @param int|null $timeout
     * @return bool
     * @throws ErrorException
     * @throws HttpException
     * @throws RpcTimeoutException
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
            $responseMessage = $this->_callbackConsumer->receive(($end - microtime(true)) * 1000);

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
                $this->_callbackConsumer->reject($responseMessage, true);

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
     * @throws ErrorException
     * @throws HttpException
     * @throws RpcTimeoutException
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