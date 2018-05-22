<?php
namespace matrozov\yii2amqp\jobs;

use Yii;
use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;

/**
 * Class ExecutedJob
 * @package matrozov\yii2amqp
 */
abstract class ExecutedJob extends BaseJob
{
    /**
     * @throws
     */
    public function exchangeName() {
        //return 'exchangeName';
        throw new ErrorException('Doesn\'t implemented!');
    }

    /**
     * @throws
     */
    public function execute() {
        throw new ErrorException('Doesn\'t implemented!');
    }

    /**
     * @param Connection|null $connection
     *
     * @return Connection
     * @throws
     */
    protected function connection(Connection $connection = null) {
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
    public function send(Connection $connection = null) {
        $connection = $this->connection($connection);

        return $connection->send($this->exchangeName(), $this);
    }
}