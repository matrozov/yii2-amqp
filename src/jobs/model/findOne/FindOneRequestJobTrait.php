<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJobTrait;

/**
 * Trait FindOneRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\findOne
 */
trait FindOneRequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function findOne(Connection $connection = null)
    {
        /** @var ModelRequestJobTrait $this */
        $response = $this->sendRequest(FindOneExecuteJob::class, $connection);

        if ($response === false) {
            return false;
        }

        return $response->result;
    }
}