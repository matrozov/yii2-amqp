<?php
namespace matrozov\yii2amqp\jobs\model\save;

use yii\db\ActiveRecord;

class ModelSaveExecuteJobActiveRecordTrait
{
    public function executeSave()
    {
        /* @var ActiveRecord $this */
        return $this->save();
    }
}