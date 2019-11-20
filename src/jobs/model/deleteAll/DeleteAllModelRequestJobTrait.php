<?php

namespace matrozov\yii2amqp\jobs\model\deleteAll;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJobTrait;
use matrozov\yii2amqp\jobs\model\ModelResponseJob;
use yii\base\ErrorException;

/**
 * Trait DeleteAllModelRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\deleteAll
 */
trait DeleteAllModelRequestJobTrait
{
    use ModelRequestJobTrait;

    /**
     * @param Connection|null $connection
     * @param string|null     $exchangeName
     *
     * @return integer|bool|null
     * @throws
     */
    public function deleteAll(Connection $connection = null, $exchangeName = null)
    {
        $response = $this->send($connection, $exchangeName);

        /* @var ModelResponseJob $response */
        if (!$this->afterModelRequest($response)) {
            return false;
        }

        if (!is_int($response->result)) {
            throw new ErrorException('Result must be integer (affected rows)!');
        }

        return $response->result;
    }
}
