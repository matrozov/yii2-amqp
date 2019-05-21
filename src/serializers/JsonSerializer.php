<?php

namespace matrozov\yii2amqp\serializers;

use matrozov\yii2amqp\jobs\BaseJob;
use Yii;
use yii\base\ErrorException;
use yii\base\Model;
use yii\helpers\Json;

/**
 * Class JsonSerializer
 * @package matrozov\yii2amqp\serializers
 */
class JsonSerializer extends Serializer
{
    /**
     * @param BaseJob $job
     *
     * @return string
     * @throws
     */
    public function serialize(BaseJob $job)
    {
        return Json::encode($this->toArray($job));
    }

    /**
     * @param string      $json
     * @param string|null $jobClassName
     *
     * @return object
     * @throws
     */
    public function deserialize($json, $jobClassName = null)
    {
        return $this->fromArray(Json::decode($json), $jobClassName);
    }
}
