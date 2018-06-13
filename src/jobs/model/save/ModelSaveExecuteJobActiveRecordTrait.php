<?php
namespace matrozov\yii2amqp\jobs\model\save;
use yii\base\ErrorException;
use yii\db\ActiveRecord;

/**
 * Trait ModelSaveExecuteJobActiveRecordTrait
 * @package matrozov\yii2amqp\jobs\model\save
 */
trait ModelSaveExecuteJobActiveRecordTrait
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