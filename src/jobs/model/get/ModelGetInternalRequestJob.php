<?php
namespace matrozov\yii2amqp\jobs\model\get;

use matrozov\yii2amqp\jobs\model\save\ModelGetRequestJob;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;
use matrozov\yii2amqp\jobs\simple\BaseJobTrait;
use yii\base\ErrorException;

class ModelGetInternalRequestJob implements RpcRequestJob, RpcExecuteJob
{
    public $className;
    public $conditions;

    public static function exchangeName()
    {
        /* @var ModelGetRequestJob $className */
        return $className::exchangeName();
    }

    public static function deliveryMode()
    {
        /* @var ModelGetRequestJob $className */
        return $className::deliveryMode();
    }

    /**
     * @return bool|ModelGetExecuteJob
     * @throws
     */
    public function execute()
    {
        /* @var ModelGetExecuteJob $modelClass */
        $modelClass = $this->className;

        /* @var ModelGetExecuteJob $model */
        $model = $modelClass::executeFindOne($this->conditions);

        if (!$model) {
            return false;
        }

        if (!($model instanceof $this->className)) {
            throw new ErrorException('Model isn\'t "' . $this->className . '"');
        }

        return $model;
    }
}