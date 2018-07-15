<?php
namespace matrozov\yii2amqp\jobs\rpc;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\simple\RequestJobTrait;
use yii\base\ErrorException;

/**
 * Trait RpcRequestJobTrait
 * @package matrozov\yii2amqp\jobs\rpc
 */
trait RpcRequestJobTrait
{
    /**
     * @param Connection $connection
     *
     * @return RpcRequestJob|bool
     * @throws ErrorException
     */
    public function send(Connection $connection = null)
    {
        $connection = Connection::instance($connection);

        /* @var RpcRequestJob $this */
        $response = $connection->send($this);

        if (!($response instanceof RpcResponseJob)) {
            throw new ErrorException('Response must be instance of RpcResponseJob');
        }

        return $response;
    }
}