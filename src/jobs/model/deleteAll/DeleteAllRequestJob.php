<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJob;

/**
 * Class DeleteAllRequestJob
 * @package matrozov\yii2amqp\jobs\model\model
 */
abstract class DeleteAllRequestJob extends ModelRequestJob
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function deleteAll(Connection $connection = null)
    {
        $response = $this->sendRequest($connection);

        if ($response === false) {
            return false;
        }

        if (!is_integer($response->result)) {
            throw new ErrorException('Result must be integer (affected rows)!');
        }

        return $response->result;
    }
}