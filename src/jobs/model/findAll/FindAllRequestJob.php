<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJob;

/**
 * Interface FindAllRequestJob
 * @package matrozov\yii2amqp\jobs\model\findAll
 */
interface FindAllRequestJob extends ModelRequestJob
{
    public function findAll(Connection $connection = null);
}