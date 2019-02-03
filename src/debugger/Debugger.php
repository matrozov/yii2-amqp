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
     * @throws \yii\base\InvalidConfigException
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
    }

    /**
     * @param string $type
     * @param mixed  $content
     */
    public function log($type, $content)
    {
        foreach ($this->targets as $target) {
            $target->log($type, $content);
        }
    }

    public function flush()
    {
        foreach ($this->targets as $target) {
            $target->flush();
        }
    }
}