<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

use yii\base\ErrorException;
use yii\db\ActiveRecord;

/**
 * Class ModelFindOneExecuteJobActiveRecordTrait
 * @package matrozov\yii2amqp\jobs\model\findOne
 */
trait ModelFindOneExecuteJobActiveRecordTrait
{
    /**
     * @param $conditions
     *
     * @return null|ActiveRecord
     * @throws
     */
    public function executeFindOne($conditions)
    {
        if (!($this instanceof ActiveRecord)) {
            throw new ErrorException('Should be ActiveRecord instance!');
        }

        /* @var ActiveRecord $this */
        return $this->findOne($conditions);
    }
}