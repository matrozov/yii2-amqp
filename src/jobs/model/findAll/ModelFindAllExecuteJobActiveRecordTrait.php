<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use yii\base\ErrorException;
use yii\db\ActiveRecord;

/**
 * Trait ModelFindAllExecuteJobActiveRecordTrait
 * @package matrozov\yii2amqp\jobs\model\findAll
 */
trait ModelFindAllExecuteJobActiveRecordTrait
{
    /**
     * @param $conditions
     *
     * @return ActiveRecord[]
     * @throws
     */
    public function executeFindAll($conditions)
    {
        if (!($this instanceof ActiveRecord)) {
            throw new ErrorException('Should be ActiveRecord instance!');
        }

        /* @var ActiveRecord $this */
        return $this->findAll($conditions);
    }
}