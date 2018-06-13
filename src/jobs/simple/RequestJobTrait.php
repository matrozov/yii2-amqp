<?php
namespace matrozov\yii2amqp\jobs\simple;

use matrozov\yii2amqp\Connection;

/**
 * Trait RequestJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait RequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return bool
     * @throws
     */
    public function send(Connection $connection = null)
    {
        $connection = Connection::instance($connection);

        /* @var RequestJob $this */
        return $connection->send($this);
    }
}