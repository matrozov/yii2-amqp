<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJobTrait;
use matrozov\yii2amqp\jobs\model\ModelResponseJob;
use yii\base\ErrorException;

/**
 * Trait FindOneModelRequestJobTrait
 * @package matrozov\yii2amqp\jobs\model\findOne
 */
trait FindOneModelRequestJobTrait
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
    public function findOne(Connection $connection = null)
    {
        if (!$this->beforeModelRequest()) {
            return false;
        }

        $response = $this->send($connection);

        /* @var ModelResponseJob $response */
        if (!$this->afterModelRequest($response)) {
            return false;
        }

        if (!is_null($response->result) && !is_array($response->result) && !is_object($response->result)) {
            throw new ErrorException('Result must be array or object!');
        }

        return $response->result;
    }
}