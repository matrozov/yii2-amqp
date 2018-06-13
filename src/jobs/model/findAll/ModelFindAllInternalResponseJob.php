<?php
namespace matrozov\yii2amqp\jobs\model\findAll;

use yii\base\Model;

/**
 * Class ModelFindAllInternalResponseJob
 * @package matrozov\yii2amqp\jobs\model\findAll
 *
 * @property Model[]|false $list
 */
class ModelFindAllInternalResponseJob
{
    public $list;
}