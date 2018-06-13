<?php
namespace matrozov\yii2amqp\jobs\modelOld\query;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;

/**
 * Class DeleteAllRequestJob
 * @package matrozov\yii2amqp\jobs\model\query
 */
abstract class DeleteAllRequestJob extends QueryRequestJob
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function deleteAll(Connection $connection = null)
    {
        $response = $this->query('deleteAll', $connection);

        if (!$response) {
            return false;
        }

        if (!is_integer($response->result)) {
            throw new ErrorException('Result must be integer (affected rows)!');
        }

        return $response->result;
    }
}