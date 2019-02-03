<?php

namespace matrozov\yii2amqp\serializers;

use matrozov\yii2amqp\jobs\BaseJob;

/**
 * Interface Serializer
 * @package matrozov\yii2amqp\serializers
 */
interface Serializer
{
    /**
     * @return string|null
     */
    public function contentType();

    /**
     * @param BaseJob $job
     *
     * @return string
     * @throws
     */
    public function serialize(BaseJob $job);

    /**
     * @param string      $data
     * @param string|null $jobClassName
     *
     * @return BaseJob
     * @throws
     */
    public function deserialize($data, $jobClassName = null);
}