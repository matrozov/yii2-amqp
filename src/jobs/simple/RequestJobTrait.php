<?php

namespace matrozov\yii2amqp\jobs\simple;

use matrozov\yii2amqp\Connection;
use yii\base\ErrorException;

/**
 * Trait RequestJobTrait
 * @package matrozov\yii2amqp\jobs\simple
 */
trait RequestJobTrait
{
    /**
     * @param Connection|null $connection
     * @param string|null     $exchangeName
     *
     * @return bool
     * @throws ErrorException
     */
    public function send(Connection $connection = null, $exchangeName = null)
    {
        $connection = Connection::instance($connection);

        /* @var RequestJob $this */
        return $connection->send($this, $exchangeName);
    }
}
