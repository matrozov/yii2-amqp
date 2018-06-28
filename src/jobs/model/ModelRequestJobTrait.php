<?php
namespace matrozov\yii2amqp\jobs\model;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;

/**
 * Trait ModelRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model
 */
trait ModelRequestJobTrait
{
    /**
     * @param string $classType
     * @param Connection|null $connection
     *
     * @var ModelRequestJob $this
     *
     * @return ModelInternalResponseJob|bool|null
     * @throws
     */
    public function sendRequest($classType, Connection $connection = null)
    {
        if (!($this instanceof ModelRequestJob)) {
            throw new ErrorException('Class must be instance of ModelRequestJob');
        }

        /** @var ModelRequestJob $this */
        if (!$this->validate()) {
            return false;
        }

        $connection = Connection::instance($connection);

        /** @var ModelRequestJob $this */
        $request = new ModelInternalRequestJob([
            'classType' => $classType,
            'className' => static::class,
            'model'     => $this,
        ]);

        /** @var ModelRequestJob $this */
        $response = $connection->send($request, $this::exchangeName());

        if (!$response) {
            return false;
        }

        if (!($response instanceof ModelInternalResponseJob)) {
            throw new ErrorException('Response should be ModelInternalResponseJob!');
        }

        if (!$response->success) {
            if (!empty($response->errors)) {
                /** @var ModelRequestJob $this */
                $this->clearErrors();
                /** @var ModelRequestJob $this */
                $this->addErrors($response->errors);
            }

            return false;
        }

        return $response;
    }
}