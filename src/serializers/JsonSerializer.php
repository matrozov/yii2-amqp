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
class JsonSerializer implements Serializer
{
    public function contentType()
    {
        return 'application/json';
    }

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
     * @param array $data
     *
     * @return array
     * @throws
     */
    protected function iterateArray($data)
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($key === 'class') {
                throw new ErrorException('Object can\'t contain `class` property!');
            }

            $result[$key] = self::toArray($value);
        }

        return $result;
    }

    /**
     * @param $data
     *
     * @return array
     * @throws
     */
    protected function toArray($data)
    {
        if ($data instanceof Model) {
            $result = $this->iterateArray($data->toArray([], [], false));

            $result['class']    = get_class($data);
            $result['scenario'] = $data->getScenario();

            return $result;
        }
        elseif (is_object($data)) {
            $result = $this->iterateArray(get_object_vars($data));

            $result['class'] = get_class($data);

            return $result;
        }
        elseif (is_array($data)) {
            return $this->iterateArray($data);
        }

        return $data;
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

    /**
     * @param string      $data
     * @param string|null $className
     *
     * @return string|array|object
     * @throws
     */
    protected function fromArray($data, $className = null)
    {
        if (!is_array($data)) {
            return $data;
        }

        $result = [];

        foreach ($data as $key => $value) {
            $result[$key] = self::fromArray($value);
        }

        if (!isset($result['class'])) {
            return $result;
        }

        if ($className) {
            $result['class'] = $className;
        }

        if (!is_subclass_of($result['class'], Model::class)) {
            return Yii::createObject($result);
        }

        /** @var Model $model */
        $model = Yii::createObject($result['class']);

        if (isset($result['scenario'])) {
            $model->setScenario($result['scenario']);
        }

        unset($result['class'], $result['scenario']);

        if (!$model->load($result, '')) {
            throw new ErrorException('Can\'t load model params');
        }

        return $model;
    }
}