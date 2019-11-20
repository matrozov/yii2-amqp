<?php

namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;

/**
 * Trait ModelRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model
 */
trait ModelRequestJobTrait
{
    /**
     * @param Connection|null $connection
     * @param string|null     $exchangeName
     *
     * @return bool|RpcResponseJob|null
     * @throws \yii\base\ErrorException
     */
    protected function send(Connection $connection = null, $exchangeName = null)
    {
        $connection = Connection::instance($connection);

        /* @var ModelRequestJob $this */
        return $connection->send($this, $exchangeName);
    }

    /**
     * @param ModelResponseJob|false|null $response
     *
     * @return bool
     */
    public function afterModelRequest($response)
    {
        if (!$response) {
            return false;
        }

        if (($response->result === false) || !empty($response->errors)) {
            /* @var ModelRequestJob $this */
            $this->addErrors($response->errors);

            return false;
        }

        return true;
    }
}
