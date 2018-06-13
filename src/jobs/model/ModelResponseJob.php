<?php
namespace matrozov\yii2amqp\jobs\model;

use matrozov\yii2amqp\jobs\rpc\RpcResponseJob;

/**
 * Class ModelResponseJob
 * @package matrozov\yii2amqp\jobs\model
 *
 * @property bool  $success
 * @property array $primaryKeys
 * @property array $errors
 */
class ModelResponseJob implements RpcResponseJob
{
    public $success     = false;
    public $primaryKeys = [];
    public $errors      = [];
}