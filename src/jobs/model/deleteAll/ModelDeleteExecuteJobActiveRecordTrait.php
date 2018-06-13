<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use yii\base\ErrorException;
use yii\db\ActiveRecord;

/**
 * Trait ModelDeleteExecuteJobActiveRecordTrait
 * @package matrozov\yii2amqp\jobs\model\deleteAll
 */
trait ModelDeleteExecuteJobActiveRecordTrait
{
    /**
     * @param $conditions
     *
     * @return int
     * @throws
     */
    public function executeDeleteAll($conditions)
    {
        if (!($this instanceof ActiveRecord)) {
            throw new ErrorException('Should be ActiveRecord instance!');
        }

        /* @var ActiveRecord $this */
        return $this::deleteAll($conditions);
    }
}