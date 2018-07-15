<?php
namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJobTrait;

/**
 * Trait ModelRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model
 */
trait ModelRequestJobTrait
{
    public function beforeModelRequest()
    {
        /* @var ModelRequestJob $this */
        $this->clearErrors();

        /* @var ModelRequestJob $this */
        return $this->validate();
    }

    protected function send(Connection $connection = null)
    {
        $connection = Connection::instance($connection);

        /* @var ModelRequestJob $this */
        return $connection->send($this);
    }

    public function afterModelRequest(ModelResponseJob $response)
    {
        if (!empty($response->errors)) {
            /* @var ModelRequestJob $this */
            $this->addErrors($response->errors);
        }

        return $response->success;
    }
}