<?php
namespace matrozov\yii2amqp\jobs\model;

/**
 * Interface ModelExecuteJob
 * @package matrozov\yii2amqp\jobs\model
 */
interface ModelExecuteJob
{
    const EXECUTE_METHOD = '';

    const EVENT_BEFORE_EXECUTE = 'beforeExecute';
    const EVENT_AFTER_EXECUTE  = 'afterExecute';

    public function validate();
    public function hasErrors();
    public function getErrors();
}