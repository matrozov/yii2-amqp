<?php

namespace matrozov\yii2amqp;

/**
 * Class ExitSignal
 * @package matrozov\yii2amqp
 */
class ExitSignal
{
    private static $exit    = false;
    private static $handled = false;

    public static function isSupported()
    {
        return extension_loaded('pcntl');
    }

    private static function attachHandler()
    {
        if (!static::isSupported() || static::$handled) {
            return;
        }

        foreach ([SIGTERM, SIGINT, SIGHUP] as $signal) {
            pcntl_signal($signal, [__NAMESPACE__ . '\ExitSignal', 'setExitFlag']);
        }

        static::$handled = true;
    }

    private static function handleSignal()
    {
        if (!static::isSupported() || static::$exit) {
            return;
        }

        pcntl_signal_dispatch();
    }

    /**
     * @return bool
     */
    public static function isExit()
    {
        static::attachHandler();
        static::handleSignal();

        return static::$exit;
    }

    public static function setExitFlag()
    {
        static::$exit = true;
    }
}