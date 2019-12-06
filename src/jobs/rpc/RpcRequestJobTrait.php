<?php

namespace matrozov\yii2amqp\jobs\rpc;

use matrozov\yii2amqp\Connection;
use yii\base\ErrorException;

/**
 * Trait RpcRequestJobTrait
 * @package matrozov\yii2amqp\jobs\rpc
 */
trait RpcRequestJobTrait
{
    /**
     * @param Connection|null $connection
     * @param string|null     $exchangeName
     *
     * @return RpcResponseJob|bool
     * @throws ErrorException
     */
    public function send(Connection $connection = null, $exchangeName = null)
    {
        $connection = Connection::instance($connection);

        /* @var RpcRequestJob $this */
        $response = $connection->send($this, $exchangeName);

        if (!($response instanceof RpcResponseJob)) {
            throw new ErrorException('Response must be instance of RpcResponseJob');
        }

        return $response;
    }
}
