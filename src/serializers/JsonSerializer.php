<?php
namespace matrozov\yii2amqp\serializers;

use matrozov\yii2amqp\jobs\BaseJob;
use Yii;
use yii\helpers\Json;
use yii\base\Model;
use yii\base\ErrorException;

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
     * @param $data
     *
     * @return array
     * @throws
     */
    protected function toArray($data)
    {
        if ($data instanceof Model) {
            $result = $data->toArray();

            if (isset($result['class'])) {
                throw new ErrorException('Model can\'t contain `class` property!');
            }

            $result['class'] = get_class($data);

            return $result;
        }

        if (is_object($data)) {
            $result = ['class' => get_class($data)];

            foreach (get_object_vars($data) as $key => $value) {
                if ($key === 'class') {
                    throw new ErrorException('Object can\'t contain `class` property!');
                }

                $result[$key] = self::toArray($value);
            }

            return $result;
        }

        if (is_array($data)) {
            $result = [];

            foreach ($data as $key => $value) {
                if ($key === 'class') {
                    throw new ErrorException('Object can\'t contain `class` property!');
                }

                $result[$key] = self::toArray($value);
            }

            return $result;
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
        $job = $this->fromArray(Json::decode($json));

        if (!($job instanceof BaseJob)) {
            throw new ErrorException('Root object must be instance of `BaseJob`!');
        }

        return $job;
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

        $object = Yii::createObject($result);

        if (($object instanceof Model) && !$object->validate()) {
            throw new ErrorException('Validate `' . get_class($object) . '` error: ' . print_r($object->errors, true));
        }

        return $object;
    }
}