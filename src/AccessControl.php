<?php

namespace matrozov\yii2amqp;

use yii\base\InvalidConfigException;
use yii\web\ForbiddenHttpException;
use yii\helpers\ArrayHelper;
use yii\web\User;
use Closure;
use Yii;

class AccessControl
{
    /**
     * @param $job
     * @throws ForbiddenHttpException
     * @throws InvalidConfigException
     */
    public static function allows($job)
    {
        $user = Yii::$app->user;

        if (self::matchRole($user, $job) && self::matchCustom($job)) {
            return;
        }

        if ($denyCallback = ArrayHelper::getValue($job->accessControl() ?? [], 'denyCallback', null)) {
            call_user_func($denyCallback);
        } else {
            self::denyAccess($user);
        }
    }

    /**
     * @param User $user
     * @param $job
     * @return bool
     * @throws InvalidConfigException
     */
    protected static function matchRole(User $user, $job)
    {
        $accessConfig = $job->accessControl();

        $items = array_merge(
            ArrayHelper::getValue($accessConfig ?? [], 'roles', []),
            ArrayHelper::getValue($accessConfig ?? [], 'permissions', [])
        );

        if (empty($items)) {
            return true;
        }

        if ($user === false) {
            throw new InvalidConfigException('The user application component must be available to specify roles in AccessRule.');
        }

        foreach ($items as $item) {
            if ($item === '?') {
                if ($user->getIsGuest()) {
                    return true;
                }
            } elseif ($item === '@') {
                if (!$user->getIsGuest()) {
                    return true;
                }
            } else {
                if (!isset($roleParams)) {
                    $configRoleParams = ArrayHelper::getValue($accessConfig ?? [], 'roleParams', []);
                    $roleParams = $configRoleParams instanceof Closure ? call_user_func($configRoleParams,
                        $job) : $configRoleParams;
                }
                if ($user->can($item, $roleParams)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param $job
     * @return bool
     */
    protected static function matchCustom($job)
    {
        $matchCallback = ArrayHelper::getValue($job->accessControl() ?? [], 'matchCallback', null);
        return empty($matchCallback) || call_user_func($matchCallback, $job);
    }

    /**
     * @param User\|false $user
     * @throws
     */
    protected static function denyAccess($user)
    {
        if ($user !== false && $user->getIsGuest()) {
            $user->loginRequired();
        } else {
            throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
        }
    }
}