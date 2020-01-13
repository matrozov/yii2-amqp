<?php

namespace matrozov\yii2amqp\serializers;

use matrozov\yii2amqp\jobs\BaseJob;

class PhpSerializer extends Serializer
{
    /**
     * @param BaseJob $job
     *
     * @return string
     * @throws
     */
    public function serialize(BaseJob $job)
    {
        return serialize($this->toArray($job));
    }

    /**
     * @param string      $text
     * @param string|null $jobClassName
     *
     * @return object
     * @throws
     */
    public function deserialize($text, $jobClassName = null)
    {
        return $this->fromArray(unserialize($text), $jobClassName);
    }
}
