<?php

namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;

/**
 * Class ModelResponseJob
 * @package matrozov\yii2amqp\jobs\model
 *
 * @property mixed $result
 * @property array $errors
 */
class ModelResponseJob implements RpcResponseJob
{
    public $result = null;
    public $errors = [];
}