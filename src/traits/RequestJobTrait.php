<?php
namespace matrozov\yii2amqp\traits;

use Yii;
use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\RequestJob;

/**
 * Trait RequestJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait RequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return Connection
     * @throws
     */
    protected function connection(Connection $connection = null)
    {
        if ($connection == null) {
            $connection = Yii::$app->amqp;
        }

        if (!$connection || !($connection instanceof Connection)) {
            throw new ErrorException('Can\'t get connection!');
        }

        return $connection;
    }

    /**
     * @param Connection|null $connection
     *
     * @return bool
     * @throws
     */
    public function send(Connection $connection = null)
    {
        $connection = $this->connection($connection);

        /* @var RequestJob $this */
        return $connection->send($this->exchangeName(), $this);
    }
}