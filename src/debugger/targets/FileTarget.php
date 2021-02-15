<?php

namespace matrozov\yii2amqp\debugger\targets;

use matrozov\yii2amqp\debugger\Target;
use Yii;
use yii\base\Exception;
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
        } else {
            $this->logFile = Yii::getAlias($this->logFile);
        }
    }

    /**
     * @param array $log
     *
     * @return string
     */
    public function formatLog(array $log): string
    {
        $time = date('Y-m-d H:i:s', $log['time']);

        $id   = $log['id'];
        $type = $log['type'];

        if (is_string($log['data'])) {
            $data = $log['data'];
        } else {
            $data = VarDumper::export($log['data']);
        }

        return sprintf("%s\t%s\t%s\t%s", $time, $id, $type, $data);
    }

    /**
     * @inheritDoc
     */
    public function logStart(string $type, string $id, array $data): void
    {
        $this->_logs[] = [
            'id'   => $id,
            'time' => microtime(true),
            'type' => $type,
            'data' => $data,
        ];
    }

    /**
     * @inheritDoc
     */
    public function logEnd(string $id, array $data): void
    {
        $this->_logs[] = [
            'id'   => $id,
            'time' => microtime(true),
            'type' => '',
            'data' => $data,
        ];
    }

    /**
     * @inheritDoc
     */
    public function log(string $type, array $data): void
    {
        $this->_logs[] = [
            'id'   => '',
            'time' => microtime(true),
            'type' => $type,
            'data' => $data,
        ];
    }

    /**
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function flush()
    {
        if (empty($this->_logs)) {
            return;
        }

        $text = implode(PHP_EOL, array_map([$this, 'formatLog'], $this->_logs)).PHP_EOL;

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

    /**
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function shutdown()
    {
        $this->flush();
    }
}
