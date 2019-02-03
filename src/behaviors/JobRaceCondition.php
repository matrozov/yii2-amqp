<?php

namespace matrozov\yii2amqp\behaviors;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\exceptions\NeedRedeliveryException;
use matrozov\yii2amqp\jobs\RequestNamedJob;
use yii\base\Behavior;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\di\Instance;
use yii\mutex\Mutex;

/**
 * Class JobRaceCondition
 * @package matrozov\yii2amqp\behaviors
 *
 * @property Mutex|array|string $mutex
 * @property int|null           $timeout
 * @property array              $attributes
 */
class JobRaceCondition extends Behavior
{
    public $mutex;
    public $timeout;
    public $attributes = [];

    protected $_locks;

    /**
     * {@inheritdoc}
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        $this->mutex = Instance::ensure($this->mutex, Mutex::class);

        if (empty($this->attributes)) {
            throw new InvalidConfigException('"attributes" required!');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function events()
    {
        return [
            Connection::EVENT_BEFORE_EXECUTE => 'beforeExecute',
            Connection::EVENT_AFTER_EXECUTE  => 'afterExecute',
        ];
    }

    /**
     * @return string[]
     */
    protected function getLockHashes()
    {
        $attr_lol = [];

        foreach ($this->attributes as $attribute) {
            if (!is_array($attribute)) {

                foreach ($attr_lol as &$attr_list) {
                    $attr_list[] = $attribute;
                }

                continue;
            }

            $attr_lol_new = [];

            foreach ($attribute as $attr) {
                $attr_lol_tmp = $attr_lol;

                foreach ($attr_lol_tmp as &$attr_list) {
                    $attr_list[] = $attr;
                }

                $attr_lol_new += $attr_lol_tmp;
            }

            $attr_lol = $attr_lol_new;
        }

        $hashes = [];

        foreach ($attr_lol as $idx => $attr_list) {
            if ($this->owner instanceof RequestNamedJob) {
                /** @var RequestNamedJob $job */
                $job = $this->owner;

                $hashes[$idx][] = $job::jobName();
            }
            else {
                $hashes[$idx][] = get_class($this->owner);
            }

            foreach ($attr_list as $attr) {
                $hashes[$idx][] = md5($this->owner->$attr);
            }

            sort($hashes[$idx]);

            $hashes[$idx] = md5(implode('', $hashes[$idx]));
        }

        return [];
    }

    /**
     * @throws ErrorException
     * @throws NeedRedeliveryException
     */
    public function beforeExecute()
    {
        if ($this->_locks) {
            throw new ErrorException('Can\'t lock twice!');
        }

        $this->_locks = $this->getLockHashes();

        foreach ($this->_locks as $lock) {
            if (!$this->mutex->acquire($lock, (int)$this->timeout)) {
                throw new NeedRedeliveryException();
            }
        }
    }

    /**
     * @throws ErrorException
     */
    public function afterExecute()
    {
        if (!$this->_locks) {
            return;
        }

        foreach ($this->_locks as $lock) {
            if (!$this->mutex->release($lock)) {
                throw new ErrorException('Can\'t release mutex!');
            }
        }

        $this->_locks = null;
    }
}