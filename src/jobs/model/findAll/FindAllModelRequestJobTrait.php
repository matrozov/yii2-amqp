<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJobTrait;
use matrozov\yii2amqp\jobs\model\ModelResponseJob;
use yii\base\ErrorException;

/**
 * Trait FindAllModelRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\findAll
 */
trait FindAllModelRequestJobTrait
{
    use ModelRequestJobTrait {
        send as protected;
    }

    /**
     * @param Connection $connection
     *
     * @return bool|\matrozov\yii2amqp\jobs\rpc\RpcResponseJob|null
     * @throws
     */
    public function findAll(Connection $connection = null)
    {
        if (!$this->beforeModelRequest()) {
            return false;
        }

        $response = $this->send($connection);

        /* @var ModelResponseJob $response */
        if (!$this->afterModelRequest($response)) {
            return false;
        }

        if (!is_array($response->result)) {
            throw new ErrorException('Result must be array!');
        }

        return $response->result;
    }
}