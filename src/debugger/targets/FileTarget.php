<?php

namespace matrozov\yii2amqp\debugger\targets;

use matrozov\yii2amqp\debugger\Target;
use Yii;
use yii\base\InvalidConfigException;
use yii\helpers\FileHelper;
use yii\helpers\VarDumper;
use yii\log\LogRuntimeException;

/**
 * Class FileTarget
 * @package matrozov\yii2amqp\debugger\targets
 *
 * @property string|null $logFile
 * @property int|null    $fileMode
 * @property int         $dirMode
 */
class FileTarget extends Target
{
    public $logFile;

    public $fileMode;
    public $dirMode = 0775;

    protected $_logs = [];

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if ($this->logFile === null) {
            $this->logFile = Yii::$app->getRuntimePath() . '/logs/amqp.log';
        }
        else {
            $this->logFile = Yii::getAlias($this->logFile);
        }
    }

    /**
     * @param array $log
     *
     * @return string
     */
    public function prepareLog($log)
    {
        $time = date('Y-m-d H:i:s', $log['time']);

        $type = $log['type'];

        if (is_string($log['content'])) {
            $content = $log['content'];
        }
        else {
            $content = VarDumper::export($log['content']);
        }

        return sprintf("%s\t%s\t%s", $time, $type, $content);
    }

    /**
     * {@inheritdoc}
     */
    public function log($type, $content)
    {
        $this->_logs[] = [
            'time'    => microtime(true),
            'type'    => $type,
            'content' => $content,
        ];
    }

    /**
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     */
    public function flush()
    {
        $text = implode(PHP_EOL, array_map([$this, 'prepareLog'], $this->_logs)) . PHP_EOL;

        $logPath = dirname($this->logFile);
        FileHelper::createDirectory($logPath, $this->dirMode, true);

        if (($file = @fopen($this->logFile, 'a')) === false) {
            throw new InvalidConfigException("Unable to append to log file: {$this->logFile}");
        }

        @flock($file, LOCK_EX);

        $writeResult = @fwrite($file, $text);

        if ($writeResult === false) {
            $error = error_get_last();

            throw new LogRuntimeException("Unable to export log through file!: {$error['message']}");
        }

        $textSize = strlen($text);

        if ($writeResult < $textSize) {
            throw new LogRuntimeException("Unable to export whole log through file! Wrote $writeResult out of $textSize bytes.");
        }

        @flock($file, LOCK_UN);
        @fclose($file);

        if ($this->fileMode !== null) {
            @chmod($this->logFile, $this->fileMode);
        }

        $this->_logs = [];
    }
}