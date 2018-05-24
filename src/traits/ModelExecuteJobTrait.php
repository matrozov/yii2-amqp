<?php
namespace matrozov\yii2amqp\traits;

use matrozov\yii2amqp\jobs\ModelExecuteJob;
use matrozov\yii2amqp\jobs\ModelResponseJob;

/**
 * Trait ModelExecuteJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait ModelExecuteJobTrait
{
    /**
     * @return ModelResponseJob
     */
    public function execute()
    {
        $response = new ModelResponseJob();

        /* @var ModelExecuteJob $this */
        $response->success = $this->validate() && $this->save();

        /* @var ModelExecuteJob $this */
        $response->errors  = $this->getErrors();

        return $response;
    }
}