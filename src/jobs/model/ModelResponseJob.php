<?php
namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;

/**
 * Class ModelResponseJob
 * @package matrozov\yii2amqp\jobs\model
 */
class ModelResponseJob implements RpcResponseJob
{
    public $success = false;
    public $result  = null;
    public $errors  = [];
}