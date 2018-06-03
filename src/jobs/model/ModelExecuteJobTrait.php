<?php
namespace matrozov\yii2amqp\jobs\model;

use yii\db\ActiveRecord;

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

        if ($response->success && ($this instanceof ActiveRecord)) {
            /* @var ActiveRecord $this */
            $response->primaryKeys = $this->getPrimaryKey(true);
        }

        /* @var ModelExecuteJob $this */
        $response->errors  = $this->getErrors();

        return $response;
    }
}