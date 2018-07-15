<?php
namespace matrozov\yii2amqp\jobs\model;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJobTrait;

/**
 * Trait ModelExecuteJobTrait
 * @package matrozov\yii2amqp\jobs\model
 */
trait ModelExecuteJobTrait
{
    use RpcExecuteJobTrait;

    public function executeRpc(Connection $connection, AmqpMessage $message)
    {
        $response = new ModelResponseJob();

        /* @var ModelExecuteJob $this */
        if ($this->validate()) {
            /* @var ModelExecuteJob $this */
            $response->result = $this->executeModel($connection, $message);
        }

        /* @var ModelExecuteJob $this */
        $response->success = !$this->hasErrors();
        /* @var ModelExecuteJob $this */
        $response->errors  = $this->getErrors();

        return $response;
    }
}