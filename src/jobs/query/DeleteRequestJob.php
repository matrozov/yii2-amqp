<?php
namespace matrozov\yii2amqp\jobs\modelOld\query;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;

/**
 * Class DeleteRequestJob
 * @package matrozov\yii2amqp\jobs\model\query
 */
abstract class DeleteRequestJob extends QueryRequestJob
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function delete(Connection $connection = null)
    {
        $response = $this->query('delete', $connection);

        if (!$response) {
            return false;
        }

        if (!is_bool($response->result)) {
            throw new ErrorException('Result must be boolean!');
        }

        return $response->result;
    }
}