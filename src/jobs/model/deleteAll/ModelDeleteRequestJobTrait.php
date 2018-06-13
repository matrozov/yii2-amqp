<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;

/**
 * Trait ModelDeleteRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\deleteAll
 */
trait ModelDeleteRequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return bool
     * @throws
     */
    public function delete(Connection $connection = null)
    {
        /* @var ModelDeleteRequestJob $this */
        return $this->deleteAll($this->primaryKeys(), $connection) > 0;
    }

    /**
     * @param                 $conditions
     * @param Connection|null $connection
     *
     * @return integer
     * @throws
     */
    public function deleteAll($conditions, Connection $connection = null)
    {
        $connection = Connection::instance($connection);

        /* @var ModelDeleteRequestJob $className */
        $className = static::class;

        $request = new ModelDeleteInternalRequestJob();
        $request->className  = $className;
        $request->conditions = $conditions;

        $response = $connection->send($request, $className::exchangeName());

        if (!$response) {
            return false;
        }

        if (!($response instanceof ModelDeleteInternalResponseJob)) {
            throw new ErrorException('Response should be ModelDeleteInternalResponseJob!');
        }

        return $response->affected;
    }
}