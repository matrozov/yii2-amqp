<?php
namespace matrozov\yii2amqp\jobs\model\get;

use yii\db\ActiveRecord;

class ModelGetExecuteJobActiveRecordTrait
{
    public function executeFindOne($conditions)
    {
        /* @var ActiveRecord $this */
        return $this->findOne($conditions);
    }
}