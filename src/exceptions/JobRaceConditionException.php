<?php

namespace matrozov\yii2amqp\exceptions;

/**
 * Class JobRaceConditionException
 * @package matrozov\yii2amqp\exceptions
 */
class JobRaceConditionException extends NeedRedeliveryException
{

}
