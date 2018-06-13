<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use matrozov\yii2amqp\jobs\model\ModelStubExchangeNameTrait;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Class ModelDeleteInternalRequestJob
 * @package matrozov\yii2amqp\jobs\model\deleteAll
 *
 * @property string $className
 * @property        $conditions
 */
class ModelDeleteInternalRequestJob implements RpcRequestJob, RpcExecuteJob
{
    use ModelStubExchangeNameTrait;

    public $className;
    public $conditions;

    /**
     * @return ModelDeleteInternalResponseJob
     */
    public function execute()
    {
        /* @var ModelDeleteExecuteJob $modelClass */
        $modelClass = $this->className;

        $response = new ModelDeleteInternalResponseJob();
        $response->success = $modelClass::executeDeleteAll($this->conditions);

        return $response;
    }
}