<?php
namespace matrozov\yii2amqp\jobs\model;

use Yii;
use yii\base\Model;
use yii\base\ErrorException;
use yii\helpers\ArrayHelper;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;

/**
 * Class ModelInternalRequestJob
 * @package matrozov\yii2amqp\jobs\model
 *
 * @property string $className
 * @property string $method
 * @property array  $data
 */
class ModelInternalRequestJob implements RpcRequestJob, RpcExecuteJob
{
    public $className;
    public $method;
    public $data;

    public static function exchangeName()
    {
        return '';
    }

    /**
     * @throws
     */
    public function execute()
    {
        /* @var Model $model */
        $model = Yii::createObject(ArrayHelper::merge(['class' => $this->className], $this->data));

        if (!($model instanceof Model)) {
            throw new ErrorException('Class must be instance of Model!');
        }

        if (!method_exists($model, $this->method)) {
            throw new ErrorException('Object must implement required method!');
        }

        $response = new ModelInternalResponseJob();

        $response->success = $model->validate();

        if ($response->success) {
            $response->result  = call_user_func([$model, $this->method]);
            $response->success = ($response->result !== false) && !$model->hasErrors();
        }

        $response->errors = $model->getErrors();

        return $response;
    }
}