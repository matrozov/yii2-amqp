<?php
namespace matrozov\yii2amqp\jobs\simple;

/**
 * Interface ExecuteJob
 * @package matrozov\yii2amqp\jobs
 */
interface ExecuteJob
{
    const EVENT_BEFORE_EXECUTE = 'beforeExecute';
    const EVENT_AFTER_EXECUTE  = 'afterExecute';

    public function execute();
}
