<?php
namespace matrozov\yii2amqp\serializers;

use Yii;
use yii\helpers\Json;
use yii\base\Model;
use yii\base\ErrorException;
use matrozov\yii2amqp\jobs\BaseJob;

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
     * @param Model $model
     *
     * @return array
     */
    protected function modelToArray(Model $model)
    {
        $data = [];

        foreach ($model->fields() as $field => $definition) {
            if (is_int($field)) {
                $field = $definition;
            }

            $data[$field] = is_string($definition) ? $model->$definition : $definition($model, $field);
        }

        return $data;
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
            $result = $this->iterateArray($this->modelToArray($data));

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
     * @param string $json
     *
     * @return object
     * @throws
     */
    public function deserialize($json)
    {
        return $this->fromArray(Json::decode($json));
    }

    /**
     * @param $data
     *
     * @return array|object
     * @throws
     */
    protected function fromArray($data)
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

        return Yii::createObject($result);
    }
}