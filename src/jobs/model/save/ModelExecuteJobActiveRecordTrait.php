<?php
namespace matrozov\yii2amqp\jobs\model\save;

use yii\db\ActiveRecord;
use yii\base\ErrorException;

/**
 * Trait ModelExecuteJobActiveRecordTrait
 * @package matrozov\yii2amqp\jobs\model\save
 */
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
        if (!$this->save()) {
            return false;
        }

        /* @var ActiveRecord $this */
        return $this->getPrimaryKey(true);
    }
}