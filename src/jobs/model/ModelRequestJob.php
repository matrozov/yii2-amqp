<?php
namespace matrozov\yii2amqp\jobs\model;

use yii\base\Model;
use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Class ModelRequestJob
 * @package matrozov\yii2amqp\jobs\model\model
 */
abstract class ModelRequestJob extends Model implements RpcRequestJob
{
    /**
     * @param Connection|null $connection
     *
     * @return ModelInternalResponseJob|bool|null
     * @throws
     */
    public function sendRequest(Connection $connection = null)
    {
        if (!$this->validate()) {
            return false;
        }

        $connection = Connection::instance($connection);

        $request = new ModelInternalRequestJob([
            'model'     => $this,

            'className' => static::class,
            'scenario'  => $this->scenario,
            'data'      => $this->toArray(),
        ]);

        $response = $connection->send($request, $this::exchangeName());

        if (!$response) {
            return false;
        }

        if (!($response instanceof ModelInternalResponseJob)) {
            throw new ErrorException('Response should be ModelInternalResponseJob!');
        }

        if (!$response->success) {
            if (!empty($response->errors)) {
                $this->clearErrors();
                $this->addErrors($response->errors);
            }

            return false;
        }

        return $response;
    }
}