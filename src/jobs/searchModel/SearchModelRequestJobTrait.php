<?php
namespace matrozov\yii2amqp\jobs\searchModel;

use Yii;
use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;

/**
 * Trait SearchModelRequestJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait SearchModelRequestJobTrait
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
     * @var SearchModelResponseJob $response
     *
     * @return bool
     * @throws
     */
    public function search(Connection $connection = null)
    {
        /* @var SearchModelRequestJob $this */
        if (!$this->validate()) {
            return false;
        }

        $connection = $this->connection($connection);

        /* @var SearchModelRequestJob $this */
        $response = $connection->send($this->exchangeName(), $this);

        if (!$response) {
            return false;
        }

        /* @var SearchModelResponseJob $response */
        /* @var SearchModelRequestJob $this */
        $this->addErrors($response->errors);

        return $response->success ? $response->items : false;
    }
}