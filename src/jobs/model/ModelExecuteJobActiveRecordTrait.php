<?php
namespace matrozov\yii2amqp\jobs\model;

use yii\db\ActiveRecord;
use yii\base\ErrorException;

trait ModelExecuteJobActiveRecordTrait
{
    /**
     * @return bool
     * @throws
     */
    public function executeSave()
    {
        if (!($this instanceof ActiveRecord)) {
            throw new ErrorException('Should be ActiveRecord instance!');
        }

        /* @var ActiveRecord $this */
        return $this->save();
    }
}