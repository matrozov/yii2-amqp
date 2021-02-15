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
     * @param string $id
     * @param array $data
     */
    abstract public function logStart(string $type, string $id, array $data): void;

    /**
     * @param string $type
     * @param string $id
     * @param array $data
     */
    abstract public function logEnd(string $id, array $data): void;

    /**
     * @param string $type
     * @param array $data
     */
    abstract public function log(string $type, array $data): void;

    /**
     * @return void
     */
    abstract public function flush();

    /**
     * @return void
     */
    abstract public function shutdown();
}
