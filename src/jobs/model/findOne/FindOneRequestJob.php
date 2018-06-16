<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJob;

/**
 * Class FindOneRequestJob
 * @package matrozov\yii2amqp\jobs\model\model
 */
abstract class FindOneRequestJob extends ModelRequestJob
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function findOne(Connection $connection = null)
    {
        $response = $this->sendRequest('executeFindOne', $connection);

        if (!$response) {
            return false;
        }

        return $response->result;
    }
}