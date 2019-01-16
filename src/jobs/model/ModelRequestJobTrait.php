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
     *
     * @return bool|RpcResponseJob|null
     * @throws
     */
    protected function send(Connection $connection = null)
    {
        $connection = Connection::instance($connection);

        /* @var ModelRequestJob $this */
        return $connection->send($this);
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