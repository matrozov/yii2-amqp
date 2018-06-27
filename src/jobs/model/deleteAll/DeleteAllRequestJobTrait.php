<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJobTrait;

/**
 * Trait DeleteAllRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\deleteAll
 */
trait DeleteAllRequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function deleteAll(Connection $connection = null)
    {
        /** @var ModelRequestJobTrait $this */
        $response = $this->sendRequest(DeleteAllExecuteJob::class, $connection);

        if ($response === false) {
            return false;
        }

        if (!is_integer($response->result)) {
            throw new ErrorException('Result must be integer (affected rows)!');
        }

        return $response->result;
    }
}