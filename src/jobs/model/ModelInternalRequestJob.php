<?php
namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\jobs\simple\EventJob;
use yii\base\Component;
use yii\base\Model;
use yii\base\ErrorException;
use matrozov\yii2amqp\jobs\rpc\RpcRequestJob;
use matrozov\yii2amqp\jobs\rpc\RpcExecuteJob;

/**
 * Class ModelInternalRequestJob
 * @package matrozov\yii2amqp\jobs\model
 *
 * @property string $classType
 * @property Model  $model
 */
class ModelInternalRequestJob extends Model implements RpcRequestJob, RpcExecuteJob
{
    /* @var Model */
    public $classType;
    public $model;

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

        if (!($this->model instanceof $classType)) {
            throw new ErrorException('Class must be instance of ModelExecuteJob!');
        }

        $response = new ModelInternalResponseJob();

        $this->beforeExecute($this->model);

        $response->success = $this->model->validate();

        if ($response->success) {
            if (!preg_match('#\\\([^\\\]*)ExecuteJob$#', $classType, $matches)) {
                throw new ErrorException('Can\'t extract method name.');
            }

            $response->result  = call_user_func([$this->model, 'execute' . $matches[1]]);
            $response->success = ($response->result !== false) && !$this->model->hasErrors();
        }

        $this->afterExecute($this->model);

        $response->errors = $this->model->getErrors();

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
}