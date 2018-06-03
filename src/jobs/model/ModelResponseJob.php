<?php
namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;
use matrozov\yii2amqp\jobs\simple\BaseJobTrait;

/**
 * Interface ModelResponseJob
 * @package matrozov\yii2amqp\jobs
 */
class ModelResponseJob implements RpcResponseJob
{
    use BaseJobTrait;

    public $success     = false;
    public $primaryKeys = [];
    public $errors      = [];
}