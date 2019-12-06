<?php

namespace matrozov\yii2amqp\jobs\model\save;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJobTrait;
use matrozov\yii2amqp\jobs\model\ModelResponseJob;

/**
 * Trait SaveModelRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\save
 */
trait SaveModelRequestJobTrait
{
    use ModelRequestJobTrait;

    /**
     * @param Connection|null $connection
     * @param string|null     $exchangeName
     *
     * @return bool|null
     * @throws
     */
    public function save(Connection $connection = null, $exchangeName = null)
    {
        /* @var ModelResponseJob $response */
        $response = $this->send($connection, $exchangeName);

        if (!$this->afterModelRequest($response)) {
            return false;
        }

        if (is_array($response->result)) {
            /** @var SaveModelRequestJob $this */
            $this->setAttributes($response->result, false);
        }

        return $response->result !== false;
    }
}
