<?php
namespace matrozov\yii2amqp\jobs\model;

use yii\db\ActiveRecord;
use yii\base\ErrorException;

trait ModelExecuteJobTrait
{
    /**
     * @return ModelResponseJob
     * @throws
     */
    public function execute()
    {
        if (!($this instanceof ModelExecuteJob)) {
            throw new ErrorException('Object must be instance of ModelExecuteJob!');
        }

        $response = new ModelResponseJob();

        /* @var ModelExecuteJob $this */
        $response->success = $this->validate() && $this->executeSave();
        /* @var ModelExecuteJob $this */
        $response->errors  = $this->getErrors();

        if ($response->success && ($this instanceof ActiveRecord)) {
            /* @var ActiveRecord $this */
            $response->primaryKeys = $this->getPrimaryKey(true);
        }

        return $response;
    }
}