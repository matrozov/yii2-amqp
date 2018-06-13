<?php
namespace matrozov\yii2amqp\jobs\rpc;

use matrozov\yii2amqp\Connection;

/**
 * Trait RpcRequestJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait RpcRequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return RpcResponseJob|bool|null
     * @throws
     */
    public function send(Connection $connection = null)
    {
        $connection = Connection::instance($connection);

        /* @var RpcRequestJob $this */
        $response = $connection->send($this);

        if (!$response) {
            return false;
        }

        if ($response instanceof RpcFalseResponseJob) {
            return false;
        }

        return $response;
    }
}