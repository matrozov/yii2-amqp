<?php
namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\jobs\rpc\RpcRequestJobTrait;

/**
 * Trait ModelRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model
 */
trait ModelRequestJobTrait
{
    use RpcRequestJobTrait {
        send as protected;
    }

    public function beforeModelRequest()
    {
        /* @var ModelRequestJob $this */
        $this->clearErrors();

        /* @var ModelRequestJob $this */
        return $this->validate();
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