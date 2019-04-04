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
     * @param Connection $connection
     *
     * @return bool
     * @throws ErrorException
     */
    public function send(Connection $connection = null)
    {
        $connection = Connection::instance($connection);

        /* @var RequestJob $this */
        return $connection->send($this);
    }
}
