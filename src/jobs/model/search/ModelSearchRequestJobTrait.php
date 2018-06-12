<?php
namespace matrozov\yii2amqp\jobs\model\search;

use Yii;
use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcFalseResponseJob;

/**
 * Trait ModelSearchRequestJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait ModelSearchRequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return Connection
     * @throws
     */
    protected static function connection(Connection $connection = null)
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
     * @param Connection|null              $connection
     *
     * @var ModelSearchInternalResponseJob $response
     *
     * @return bool
     * @throws
     */
    public function search(Connection $connection = null)
    {
        /* @var ModelSearchRequestJob $this */
        if (!$this->validate()) {
            return false;
        }

        $connection = static::connection($connection);

        /* @var ModelSearchRequestJob $this */
        $response = $connection->send($this);

        if (!$response) {
            return false;
        }

        if ($response instanceof RpcFalseResponseJob) {
            return false;
        }

        if (!($response instanceof ModelSearchInternalResponseJob)) {
            throw new ErrorException('Response isn\'t ModelSearchInternalResponseJob');
        }

        /* @var ModelSearchInternalResponseJob $response */
        /* @var ModelSearchRequestJob $this */
        $this->addErrors($response->errors);

        return $response->success ? $response->items : false;
    }
}