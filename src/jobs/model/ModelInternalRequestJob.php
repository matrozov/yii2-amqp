<?php
namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\jobs\simple\EventJob;
use Yii;
use yii\base\Component;
use yii\base\Model;
use yii\base\ErrorException;
use yii\helpers\ArrayHelper;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;

/**
 * Class ModelInternalRequestJob
 * @package matrozov\yii2amqp\jobs\model
 *
 * @property Model  $model
 *
 * @property string $className
 * @property string $scenario
 * @property array  $data
 */
class ModelInternalRequestJob extends Model implements RpcRequestJob, RpcExecuteJob
{
    /* @var Model */
    public $model;

    public $classType;
    public $className;
    public $scenario;
    public $data;

    public function init()
    {
        parent::init();

        $this->on(self::EVENT_BEFORE_SEND, [$this, 'handleEvent']);
        $this->on(self::EVENT_AFTER_SEND, [$this, 'handleEvent']);
    }

    public function handleEvent(EventJob $event)
    {
        $this->model->trigger($event->name, $event);
    }

    public static function exchangeName()
    {
        return '';
    }

    /**
     * @throws
     */
    public function execute()
    {
        /** @var ModelExecuteJob $classType */
        $classType = $this->classType;

        /* @var Model $model */
        $model = Yii::createObject(ArrayHelper::merge(['class' => $this->className, 'scenario' => $this->scenario], $this->data));

        if (!($model instanceof $classType)) {
            throw new ErrorException('Class must be instance of ModelExecuteJob!');
        }

        $response = new ModelInternalResponseJob();

        $this->beforeExecute($model);

        $response->success = $model->validate();

        if ($response->success) {
            /** @var ModelExecuteJob $model */
            $response->result  = call_user_func([$model, $classType::EXECUTE_METHOD]);
            $response->success = ($response->result !== false) && !$model->hasErrors();
        }

        $this->afterExecute($model);

        $response->errors = $model->getErrors();

        return $response;
    }

    public function beforeExecute($model)
    {
        if (!($model instanceof Component)) {
            return;
        }

        $event = new EventJob([
            'job' => $model,
        ]);

        /* @var Component $model */
        $model->trigger(static::EVENT_BEFORE_EXECUTE, $event);
    }

    public function afterExecute($model)
    {
        if (!($model instanceof Component)) {
            return;
        }

        $event = new EventJob([
            'job' => $model,
        ]);

        /* @var Component $model */
        $model->trigger(static::EVENT_AFTER_EXECUTE, $event);
    }

    public function fields()
    {
        return [
            'className',
            'scenario',
            'data',
        ];
    }
}