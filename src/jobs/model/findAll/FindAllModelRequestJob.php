<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJob;

/**
 * Interface FindAllModelRequestJob
 * @package matrozov\yii2amqp\jobs\model\findAll
 */
interface FindAllModelRequestJob extends ModelRequestJob
{
    public function findAll(Connection $connection = null);
}