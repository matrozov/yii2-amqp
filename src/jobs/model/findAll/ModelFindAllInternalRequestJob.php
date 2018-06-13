<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use matrozov\yii2amqp\jobs\model\ModelStubExchangeNameTrait;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Class ModelFindAllInternalRequestJob
 * @package matrozov\yii2amqp\jobs\model\findAll
 *
 * @property string $className
 * @property        $conditions
 */
class ModelFindAllInternalRequestJob implements RpcRequestJob, RpcExecuteJob
{
    use ModelStubExchangeNameTrait;

    public $className;
    public $conditions;

    /**
     * @return ModelFindAllInternalResponseJob
     */
    public function execute()
    {
        /* @var ModelFindAllExecuteJob $modelClass */
        $modelClass = $this->className;

        $response = new ModelFindAllInternalResponseJob();
        $response->list = $modelClass::executeFindAll($this->conditions);

        return $response;
    }
}