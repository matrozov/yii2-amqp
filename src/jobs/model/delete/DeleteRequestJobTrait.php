<?php
namespace matrozov\yii2amqp\jobs\model\delete;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJobTrait;

/**
 * Trait DeleteRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\delete
 */
trait DeleteRequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function delete(Connection $connection = null)
    {
        /** @var ModelRequestJobTrait $this */
        $response = $this->sendRequest(DeleteExecuteJob::class, $connection);

        if ($response === false) {
            return false;
        }

        if (!is_bool($response->result)) {
            throw new ErrorException('Result must be boolean!');
        }

        return $response->result;
    }
}