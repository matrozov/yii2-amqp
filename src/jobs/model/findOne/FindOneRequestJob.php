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
    /**
     * [!] Use FindOneRequestJobTrait
     *
     * @param Connection|null $connection
     *
     * @return array|object|bool
     * @throws
     */
    public function findOne(Connection $connection = null);
}