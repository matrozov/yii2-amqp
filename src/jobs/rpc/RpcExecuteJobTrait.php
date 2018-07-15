<?php
namespace matrozov\yii2amqp\jobs\rpc;

use Interop\Amqp\AmqpMessage;
use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\simple\ExecuteJobTrait;
use yii\base\ErrorException;

/**
 * Trait RpcExecuteJobTrait
 * @package matrozov\yii2amqp\jobs\rpc
 */
trait RpcExecuteJobTrait
{
    use ExecuteJobTrait;

    /**
     * @param Connection  $connection
     * @param AmqpMessage $message
     *
     * @return RpcResponseJob
     * @throws
     */
    public function execute(Connection $connection, AmqpMessage $message)
    {
        /* @var RpcExecuteJob $this */
        $data = $this->executeRpc($connection, $message);

        if (!($data instanceof RpcResponseJob)) {
            throw new ErrorException('Response must be instance of RpcResponseJob');
        }

        return $data;
    }
}