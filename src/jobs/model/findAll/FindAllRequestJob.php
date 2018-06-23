<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJob;

/**
 * Class FindAllRequestJob
 * @package matrozov\yii2amqp\jobs\model\model
 */
abstract class FindAllRequestJob extends ModelRequestJob
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function findAll(Connection $connection = null)
    {
        $response = $this->sendRequest($connection);

        if ($response === false) {
            return false;
        }

        if (!is_array($response->result)) {
            throw new ErrorException('Result must be array!');
        }

        return $response->result;
    }
}