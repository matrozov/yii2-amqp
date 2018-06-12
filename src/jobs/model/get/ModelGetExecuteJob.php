<?php
namespace matrozov\yii2amqp\jobs\model\get;

/**
 * Interface ModelGetExecuteJob
 * @package matrozov\yii2amqp\jobs\model\find
 */
interface ModelGetExecuteJob
{
    public static function get($conditions);
}