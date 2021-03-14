<?php

namespace matrozov\yii2amqp\debugger;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\di\Instance;

/**
 * Class Debugger
 * @package matrozov\yii2amqp\debug
 *
 * @property Target[] $targets
 */
class Debugger extends Component
{
    public $targets = [];

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if (empty($this->targets)) {
            throw new InvalidConfigException('"targets" empty!');
        }

        foreach ($this->targets as &$target) {
            $target = Instance::ensure($target, Target::class);
        }

        register_shutdown_function([$this, 'shutdown']);
    }

    /**
     * @param string $id
     * @param string $type
     * @param array  $data
     */
    public function event(string $id, string $type, array $data): void
    {
        foreach ($this->targets as $target) {
            $target->event($id, $type, $data);
        }
    }

    /**
     * @param string $type
     * @param string $id
     * @param array  $data
     */
    public function logStart(string $type, string $id, array $data): void
    {
        foreach ($this->targets as $target) {
            $target->logStart($type, $id, $data);
        }
    }

    /**
     * @param string $type
     * @param string $id
     * @param array  $data
     */
    public function logEnd(string $type, string $id, array $data): void
    {
        foreach ($this->targets as $target) {
            $target->logEnd($type, $id, $data);
        }
    }

    /**
     * @param string $type
     * @param array  $data
     */
    public function log(string $type, array $data): void
    {
        foreach ($this->targets as $target) {
            $target->log($type, $data);
        }
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        foreach ($this->targets as $target) {
            $target->flush();
        }
    }

    /**
     * @return void
     */
    public function shutdown(): void
    {
        foreach ($this->targets as $target) {
            $target->shutdown();
        }
    }
}
