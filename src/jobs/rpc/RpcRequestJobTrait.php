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
    use RequestJobTrait {
        send as protected sendSimple;
    }

    /**
     * @param Connection $connection
     *
     * @return bool
     * @throws ErrorException
     */
    public function send(Connection $connection = null)
    {
        $response = $this->sendSimple($connection);

        if (!($response instanceof RpcResponseJob)) {
            throw new ErrorException('Response must be instance of RpcResponseJob');
        }

        return $response;
    }
}