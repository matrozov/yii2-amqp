<?php
namespace matrozov\yii2amqp\jobs\model\save;

use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;
use matrozov\yii2amqp\jobs\simple\BaseJobTrait;

/**
 * Interface ModelSaveInternalResponseJob
 * @package matrozov\yii2amqp\jobs
 */
class ModelSaveInternalResponseJob implements RpcResponseJob
{
    use BaseJobTrait;

    public $success     = false;
    public $primaryKeys = [];
    public $errors      = [];
}