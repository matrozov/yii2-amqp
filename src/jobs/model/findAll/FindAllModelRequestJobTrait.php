<?php

namespace matrozov\yii2amqp\jobs\model\findAll;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJobTrait;
use matrozov\yii2amqp\jobs\model\ModelResponseJob;
use yii\base\ErrorException;

/**
 * Trait FindAllModelRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\findAll
 */
trait FindAllModelRequestJobTrait
{
    use ModelRequestJobTrait;

    /**
     * @param Connection $connection
     *
     * @return array|bool|null
     * @throws
     */
    public function findAll(Connection $connection = null)
    {
        $response = $this->send($connection);

        /* @var ModelResponseJob $response */
        if (!$this->afterModelRequest($response)) {
            return false;
        }

        if (!is_array($response->result)) {
            throw new ErrorException('Result must be array!');
        }

        return $response->result;
    }
}