<?php
namespace matrozov\yii2amqp\jobs\model\save;

use matrozov\yii2amqp\jobs\model\get\ModelGetInternalRequestJob;
use matrozov\yii2amqp\jobs\rpc\RpcFalseResponseJob;
use Yii;
use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;

/**
 * Trait ModelGetRequestJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait ModelGetRequestJobTrait
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
     * @param                 $conditions
     * @param Connection|null $connection
     *
     * @return bool|\matrozov\yii2amqp\jobs\rpc\RpcResponseJob|null
     * @throws
     */
    public static function get($conditions, Connection $connection = null)
    {
        $connection = static::connection($connection);

        $request = new ModelGetInternalRequestJob();
        $request->className  = static::class;
        $request->conditions = $conditions;

        /* @var ModelGetRequestJob $this */
        $response = $connection->send($request);

        if (!$response) {
            return false;
        }

        if ($response instanceof RpcFalseResponseJob) {
            return false;
        }

        if (!($response instanceof static)) {
            throw new ErrorException('Response isn\'t "' . static::class . '"');
        }

        return $response;
    }
}