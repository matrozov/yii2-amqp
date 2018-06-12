<?php
namespace matrozov\yii2amqp\jobs\model\save;

use yii\db\ActiveRecord;

/**
 * Trait ModelSaveExecuteJobTrait
 * @package matrozov\yii2amqp\traits
 */
trait ModelSaveExecuteJobTrait
{
    /**
     * @return ModelSaveInternalResponseJob
     */
    public function execute()
    {
        $response = new ModelSaveInternalResponseJob();

        /* @var ModelSaveExecuteJob $this */
        $response->success = $this->validate() && $this->executeSave();

        if ($response->success && ($this instanceof ActiveRecord)) {
            /* @var ActiveRecord $this */
            $response->primaryKeys = $this->getPrimaryKey(true);
        }

        /* @var ModelSaveExecuteJob $this */
        $response->errors  = $this->getErrors();

        return $response;
    }
}