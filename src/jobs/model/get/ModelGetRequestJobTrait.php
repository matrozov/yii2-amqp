<?php
namespace matrozov\yii2amqp\jobs\model\get;

use Yii;
use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcFalseResponseJob;
use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;

/**
 * Trait ModelGetRequestJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait ModelGetRequestJobTrait
{
    /**
     * @param                 $conditions
     * @param Connection|null $connection
     *
     * @return bool|RpcResponseJob|null
     * @throws
     */
    public static function findOne($conditions, Connection $connection = null)
    {
        $connection = Connection::instance($connection);

        $request = new ModelGetInternalRequestJob();
        $request->className  = static::class;
        $request->conditions = $conditions;

        $response = $connection->send($request, static::exchangeName());

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