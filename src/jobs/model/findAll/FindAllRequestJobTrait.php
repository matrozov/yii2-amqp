<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use yii\base\ErrorException;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJobTrait;

/**
 * Trait FindAllRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\findAll
 */
trait FindAllRequestJobTrait
{
    /**
     * @param Connection|null $connection
     *
     * @return array|bool|null
     * @throws ErrorException
     */
    public function findAll(Connection $connection = null)
    {
        /** @var ModelRequestJobTrait $this */
        $response = $this->sendRequest(FindAllExecuteJob::class, $connection);

        if ($response === false) {
            return false;
        }

        if (!is_array($response->result)) {
            throw new ErrorException('Result must be array!');
        }

        return $response->result;
    }
}