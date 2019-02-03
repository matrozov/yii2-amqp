<?php

namespace matrozov\yii2amqp\debugger;

use yii\base\Component;

/**
 * Class Target
 * @package matrozov\yii2amqp\debug
 */
abstract class Target extends Component
{
    /**
     * @param string $type
     * @param mixed  $content
     */
    abstract public function log($type, $content);

    abstract public function flush();
}