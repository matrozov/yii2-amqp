<?php
namespace matrozov\yii2amqp\jobs;

/**
 * Interface ExpiredJob
 * @package matrozov\yii2amqp\jobs
 */
interface ExpiredJob
{
    /**
     * @param float|int|null $ttl
     *
     * @return static
     */
    public function setTtl($ttl = null);

    /**
     * @return float|int|null
     */
    public function getTtl();
}