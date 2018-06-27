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
    /**
     * [!] Use FindAllRequestJobTrait
     *
     * @param Connection|null $connection
     *
     * @return array|bool
     * @throws
     */
    public function findAll(Connection $connection = null);
}