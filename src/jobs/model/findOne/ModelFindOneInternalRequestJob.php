<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

use matrozov\yii2amqp\jobs\model\ModelStubExchangeNameTrait;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;
use yii\base\ErrorException;

/**
 * Class ModelFindOneInternalRequestJob
 * @package matrozov\yii2amqp\jobs\model\findOne
 *
 * @property string $className
 * @property        $conditions
 */
class ModelFindOneInternalRequestJob implements RpcRequestJob, RpcExecuteJob
{
    use ModelStubExchangeNameTrait;

    public $className;
    public $conditions;

    public function execute()
    {
        /* @var ModelFindOneExecuteJob $modelClass */
        $modelClass = $this->className;

        $model = $modelClass::executeFindOne($this->conditions);

        if (!$model) {
            return false;
        }

        if (!($model instanceof $this->className)) {
            throw new ErrorException('Model isn\'t "' . $this->className . '"');
        }

        $response = new ModelFindOneInternalResponseJob();
        $response->model = $model;

        return $response;
    }
}