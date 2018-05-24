<?php
namespace matrozov\yii2amqp\jobs;
use matrozov\yii2amqp\traits\BaseJobTrait;

/**
 * Interface ModelResponseJob
 * @package matrozov\yii2amqp\jobs
 */
class ModelResponseJob implements RpcResponseJob
{
    use BaseJobTrait;

    public $success;
    public $errors;
}