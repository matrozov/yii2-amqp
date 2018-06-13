<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use matrozov\yii2amqp\Connection;
use yii\base\ErrorException;

/**
 * Trait ModelFindAllRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\findAll
 */
trait ModelFindAllRequestJobTrait
{
    /**
     * @param                 $conditions
     * @param Connection|null $connection
     *
     * @return bool
     * @throws
     */
    public static function findAll($conditions, Connection $connection = null)
    {
        $connection = Connection::instance($connection);

        /* @var ModelFindAllRequestJob $className */
        $className = static::class;

        $request = new ModelFindAllInternalRequestJob();
        $request->className  = $className;
        $request->conditions = $conditions;

        $response = $connection->send($request, $className::exchangeName());

        if (!$response) {
            return false;
        }

        if (!($response instanceof ModelFindAllInternalResponseJob)) {
            throw new ErrorException('Response should be ModelFindAllInternalResponseJob!');
        }

        return $response->list;
    }
}