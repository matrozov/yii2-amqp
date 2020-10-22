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
    protected $_start;
    /** @var float */
    protected $_timeout;
    /** @var int */
    protected $_success;

    public function __construct($connection, $callbackConsumer, $linked)
    {
        $this->_connection       = $connection;
        $this->_callbackConsumer = $callbackConsumer;
        $this->_linked           = $linked;

        $this->_start   = microtime(true);
        $this->_timeout = $this->_connection->rpcTimeout;
        $this->_success = 0;
    }

    /**
     * @return bool
     * @throws ErrorException
     * @throws RpcTimeoutException
     * @throws HttpException
     */
    public function isReady()
    {
        while ($this->_success < count($this->_linked)) {
            $responseMessage = $this->_callbackConsumer->receiveNoWait();

            if (!$responseMessage) {
                break;
            }

            $correlationId = $responseMessage->getCorrelationId();

            if (!array_key_exists($correlationId, $this->_linked)) {
                $this->_callbackConsumer->reject($responseMessage, false);

                if ($this->_timeout !== null) {
                    $this->_timeout -= (microtime(true) - $this->_start);

                    if ($this->_timeout < 0) {
                        throw new RpcTimeoutException('Queue timeout');
                    }
                }
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

        if ($this->_timeout !== null) {
            $this->_timeout -= (microtime(true) - $this->_start);

            if ($this->_timeout < 0) {
                throw new RpcTimeoutException('Queue timeout');
            }
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