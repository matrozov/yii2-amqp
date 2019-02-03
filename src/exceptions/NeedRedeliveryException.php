<?php

namespace matrozov\yii2amqp\exceptions;

use matrozov\yii2amqp\jobs\SilentJobException;
use yii\base\Exception;

/**
 * Class NeedRedeliveryException
 * @package matrozov\yii2amqp\exceptions
 */
class NeedRedeliveryException extends Exception implements SilentJobException
{

}