<?php
namespace matrozov\yii2amqp\jobs\model\save;

use yii\base\Model;
use yii\db\ActiveRecord;
use matrozov\yii2amqp\jobs\model\ModelStubExchangeNameTrait;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;

/**
 * Class ModelSaveInternalRequestJob
 * @package matrozov\yii2amqp\jobs\model\save
 *
 * @property string $className
 * @property array  $attributes
 */
class ModelSaveInternalRequestJob implements RpcRequestJob, RpcExecuteJob
{
    use ModelStubExchangeNameTrait;

    public $className;
    public $attributes;

    /**
     * @return ModelSaveInternalResponseJob
     */
    public function execute()
    {
        /* @var ModelSaveExecuteJob $modelClass */
        $modelClass = $this->className;

        $response = new ModelSaveInternalResponseJob();

        /* @var Model $model */
        $model = new $modelClass;

        $response->success = $model->load($this->attributes, '');
        $response->success = $response->success && $model->validate();

        /* @var ModelSaveExecuteJob $model */
        $response->success = $response->success && $model->executeSave();
        $response->errors  = $model->getErrors();

        if ($response->success && ($this instanceof ActiveRecord)) {
            /* @var ActiveRecord $this */
            $response->primaryKeys = $this->getPrimaryKey(true);
        }

        return $response;
    }
}