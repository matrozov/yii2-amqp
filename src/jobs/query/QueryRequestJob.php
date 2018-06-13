<?php
namespace matrozov\yii2amqp\jobs\modelOld\query;

use matrozov\yii2amqp\jobs\query\QueryInternalRequestJob;
use yii\base\Model;
use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Class QueryRequestJob
 * @package matrozov\yii2amqp\jobs\model\query
 */
abstract class QueryRequestJob extends Model implements RpcRequestJob
{
    /**
     * @param string          $method
     * @param Connection|null $connection
     *
     * @return QueryInternalResponseJob|bool|null
     * @throws
     */
    protected function query($method, Connection $connection = null)
    {
        if (!$this->validate()) {
            return false;
        }

        $connection = Connection::instance($connection);

        $request = new QueryInternalRequestJob();
        $request->className  = static::class;
        $request->method     = $method;
        $request->conditions = $this->toArray();

        $response = $connection->send($request, $this::exchangeName());

        if (!$response) {
            return false;
        }

        if (!($response instanceof QueryInternalResponseJob)) {
            throw new ErrorException('Response should be QueryInternalResponseJob!');
        }

        if (!$response->success) {
            if (!empty($response->errors)) {
                $this->clearErrors();
                $this->addErrors($response->errors);
            }

            return false;
        }

        return $response;
    }
}