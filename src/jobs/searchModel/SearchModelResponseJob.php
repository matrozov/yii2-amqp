<?php
namespace matrozov\yii2amqp\jobs\searchModel;

use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;
use matrozov\yii2amqp\jobs\simple\BaseJobTrait;

/**
 * Interface SearchModelResponseJob
 * @package matrozov\yii2amqp\jobs
 */
class SearchModelResponseJob implements RpcResponseJob
{
    use BaseJobTrait;

    public $success;
    public $items;
    public $errors;
}