<?php
namespace matrozov\yii2amqp\jobs\model\save;

/**
 * Class ModelSaveInternalResponseJob
 * @package matrozov\yii2amqp\jobs\model\save
 *
 * @property boolean $success
 * @property array   $primaryKeys
 * @property array   $errors
 */
class ModelSaveInternalResponseJob
{
    public $success     = false;
    public $primaryKeys = [];
    public $errors      = [];
}