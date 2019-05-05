<?php

namespace matrozov\yii2amqp\jobs;

/**
 * Trait ExpiredJobTrait
 * @package matrozov\yii2amqp\jobs
 *
 * @property float|int|null $ttl
 */
trait ExpiredJobTrait
{
    /** @var float|int|null $ttl */
    private $ttl;

    /**
     * @param float|int|null $ttl
     *
     * @return static
     */
    public function setTtl($ttl = null)
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * @return float|int|null
     */
    public function getTtl()
    {
        return $this->ttl;
    }
}