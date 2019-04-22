<?php

namespace matrozov\yii2amqp\behaviors;

use matrozov\yii2amqp\Connection;
use matrozov\yii2amqp\exceptions\NeedRedeliveryException;
use matrozov\yii2amqp\jobs\RequestNamedJob;
use matrozov\yii2amqp\jobs\simple\ExecuteJob;
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
 *                                         ['attr_1']                       =>          => [['attr_1']]
 *                                         ['attr_1', 'attr_2']             => AND      => [['attr_1', 'attr_2']]
 *                                         [['attr_1', 'attr_2']]           => OR       => [['attr_1'], ['attr_2']]
 *                                         ['attr_1', ['attr_2', 'attr_3']] => AND + OR => [['attr_1', 'attr_2'], ['attr_1', 'attr_3']]
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
     * @throws InvalidConfigException
     */
    public function attach($owner)
    {
        if (!($owner instanceof ExecuteJob)) {
            throw new InvalidConfigException('You should attach behavior only instanceof ExecuteJob!');
        }

        parent::attach($owner);
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
        $chain = [];

        foreach ($this->attributes as $attribute) {
            $attribute = (array) $attribute;
            $chain_new = [];

            foreach ($attribute as $attr) {
                if (empty($chain)) {
                    $chain_new[] = [$attr];
                } else {
                    foreach ($chain as $key => $item) {
                        $chain_new[] = array_merge($item, [$attr]);
                    }
                }
            }

            $chain = $chain_new;
        }

        $hashes = [];

        foreach ($chain as $idx => $attr_list) {
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

        return $hashes;
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
