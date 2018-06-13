<?php
namespace matrozov\yii2amqp\jobs\modelOld\query;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;

/**
 * Class FindOneRequestJob
 * @package matrozov\yii2amqp\jobs\model\query
 */
abstract class FindOneRequestJob extends QueryRequestJob
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function findOne(Connection $connection = null)
    {
        $response = $this->query('findOne', $connection);

        if (!$response) {
            return false;
        }

        return $response->result;
    }
}