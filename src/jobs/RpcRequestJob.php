<?php
namespace matrozov\yii2amqp\jobs;

use matrozov\yii2amqp\Connection;

/**
 * Class RpcRequestJob
 * @package matrozov\yii2amqp
 */
abstract class RpcRequestJob extends ExecutedJob
{
    /**
     * @param Connection|null $connection
     * @param null            $timeout
     *
     * @return RpcResponseJob|null
     * @throws
     */
    public function send(Connection $connection = null, $timeout = null) {
        $connection = $this->connection($connection);

        return $connection->send($this->exchangeName(), $this, $timeout);
    }
}