<?php
namespace matrozov\yii2amqp\jobs\model;

use yii\base\Model;
use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;

/**
 * Trait ModelRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model
 */
trait ModelRequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return bool|null
     * @throws
     */
    public function save(Connection $connection = null)
    {
        if (!($this instanceof ModelRequestJob)) {
            throw new ErrorException('Object must be instance of ModelRequestJob!');
        }

        /* @var Model $this */
        if (!$this->validate()) {
            return false;
        }

        $connection = Connection::instance($connection);

        /* @var ModelRequestJob $this */
        $response = $connection->send($this);

        if (!$response) {
            return false;
        }

        if (!($response instanceof ModelResponseJob)) {
            throw new ErrorException('Response should be ModelResponseJob!');
        }

        /* @var ModelRequestJob $this */
        $this->clearErrors();
        /* @var ModelRequestJob $this */
        $this->addErrors($response->errors);

        if (!$response->success) {
            return false;
        }

        foreach ($response->primaryKeys as $key => $value) {
            $this->$key = $value;
        }

        return $response->success;
    }
}