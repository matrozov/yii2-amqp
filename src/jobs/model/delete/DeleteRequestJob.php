<?php
namespace matrozov\yii2amqp\jobs\model\delete;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJob;

/**
 * Class DeleteRequestJob
 * @package matrozov\yii2amqp\jobs\model\model
 */
abstract class DeleteRequestJob extends ModelRequestJob
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function delete(Connection $connection = null)
    {
        $response = $this->sendRequest('executeDelete', $connection);

        if (!$response) {
            return false;
        }

        if (!is_bool($response->result)) {
            throw new ErrorException('Result must be boolean!');
        }

        return $response->result;
    }
}