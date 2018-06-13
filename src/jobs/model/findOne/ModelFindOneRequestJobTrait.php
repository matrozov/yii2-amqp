<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcFalseResponseJob;
use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;

/**
 * Trait ModelFindOneRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\findOne
 */
trait ModelFindOneRequestJobTrait
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

        /* @var ModelFindOneRequestJob $className */
        $className = static::class;

        $request = new ModelFindOneInternalRequestJob();
        $request->className  = $className;
        $request->conditions = $conditions;

        $response = $connection->send($request, $className::exchangeName());

        if (!$response) {
            return false;
        }

        if ($response instanceof RpcFalseResponseJob) {
            return false;
        }

        if (!($response instanceof ModelFindOneInternalResponseJob)) {
            throw new ErrorException('Response should be ModelFindOneInternalResponseJob!');
        }

        if (!($response->model instanceof $className)) {
            throw new ErrorException('Response model should be ' . $className . '!');
        }

        return $response->model;
    }
}