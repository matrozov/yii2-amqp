<?php
namespace matrozov\yii2amqp\jobs\model\deleteAll;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\jobs\model\ModelRequestJob;

/**
 * Interface DeleteAllRequestJob
 * @package matrozov\yii2amqp\jobs\model\deleteAll
 */
interface DeleteAllRequestJob extends ModelRequestJob
{
    /**
     * [!] Use DeleteAllRequestJob
     *
     * @param Connection|null $connection
     *
     * @return integer|bool
     * @throws
     */
    public function deleteAll(Connection $connection = null);
}