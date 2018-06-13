<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

/**
 * Interface ModelFindOneExecuteJob
 * @package matrozov\yii2amqp\jobs\model\findOne
 */
interface ModelFindOneExecuteJob
{
    public static function executeFindOne($conditions);
}