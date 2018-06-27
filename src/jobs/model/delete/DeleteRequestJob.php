<?php
namespace matrozov\yii2amqp\jobs\model\delete;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJob;

/**
 * Interface DeleteRequestJob
 * @package matrozov\yii2amqp\jobs\model\delete
 */
interface DeleteRequestJob extends ModelRequestJob
{
    public function delete(Connection $connection = null);
}