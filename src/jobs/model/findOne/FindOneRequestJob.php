<?php
namespace matrozov\yii2amqp\jobs\model\findOne;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJob;

/**
 * Interface FindOneRequestJob
 * @package matrozov\yii2amqp\jobs\model\findOne
 */
interface FindOneRequestJob extends ModelRequestJob
{
    public function findOne(Connection $connection = null);
}