<?php
namespace matrozov\yii2amqp;

use Yii;
use yii\base\BaseObject;
use yii\base\ErrorException;
use yii\base\Model;
use yii\helpers\Json;

/**
 * Class BaseJob
 * @package matrozov\yii2amqp
 */
abstract class BaseJob extends BaseObject
{
    /**
     * @return string
     * @throws
     */
    public function encode() {
        return Json::encode(static::toArray($this));
    }

    /**
     * @param $data
     *
     * @return array
     * @throws
     */
    protected static function toArray($data) {
        if ($data instanceof Model) {
            $result = $data->toArray();

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
     * @return BaseJob
     * @throws
     */
    public static function decode($json) {
        $job = self::fromArray(Json::decode($json));

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
    protected static function fromArray($data) {
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