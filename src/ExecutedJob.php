<?php
namespace matrozov\yii2amqp;

use Yii;
use yii\base\BaseObject;
use yii\base\ErrorException;

/**
 * Class ExecutedJob
 * @package matrozov\yii2amqp
 */
abstract class ExecutedJob extends BaseJob
{
    public function execute() {
        throw new ErrorException('Doesn\'t implemented!');
    }
}