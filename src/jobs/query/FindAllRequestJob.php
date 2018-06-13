<?php
namespace matrozov\yii2amqp\jobs\query;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;

/**
 * Class FindAllRequestJob
 * @package matrozov\yii2amqp\jobs\model\query
 */
abstract class FindAllRequestJob extends QueryRequestJob
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function findAll(Connection $connection = null)
    {
        $response = $this->query('findAll', $connection);

        if (!$response) {
            return false;
        }

        if (!is_array($response->result)) {
            throw new ErrorException('Result must be array!');
        }

        return $response->result;
    }
}