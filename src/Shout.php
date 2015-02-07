<?php
namespace noFlash\Shout;

use Psr\Log\InvalidArgumentException;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Simple, light but powerful logger object
 *
 * Class Shout
 */
class Shout extends AbstractLogger implements LoggerInterface
{
    const FILE_OVERWRITE = "w";
    const FILE_APPEND    = "a";
    private $validWriteModes = array("r+", "w", "w+", "a", "a+", "x", "x+", "c", "c+");

    const EMERGENCY = 'EMERG';
    const ALERT     = 'ALERT';
    const CRITICAL  = 'CRITICAL';
    const ERROR     = 'ERROR';
    const WARNING   = 'WARN';
    const NOTICE    = 'NOTICE';
    const INFO      = 'INFO';
    const DEBUG     = 'DEBUG';

    private $config = array(
        "destination" => "php://stdout", //Variables: %s - date, %d - unix timestamp
        "writeMode" => self::FILE_APPEND,
        "blocking" => true,
        "rotateEnabled" => false,
        "rotationInterval" => 86400,
        "lineFormat" => "<%1\$s> [%2\$s] %3\$s [%4\$s]\n", //%1$s - date, %2$s - log level, %3$s - text, %4$s - context, %d - unix timestamp
        "datetimeFormat" => "d.m.Y H:i:s",
        "levelsPriorities" => array(
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7
        ),
        "maximumLogLevel" => 999
    );

    private   $destinationHandler;
    private   $lastRotationTime;
    private   $asyncBuffer;

    /**
     * @param string $destination
     * @param string $mode
     *
     * @throws InvalidArgumentException Invalid destination or mode
     * @see setDestination()
     * @see setWriteMode()
     */
    public function __construct($destination = null, $mode = null)
    {
        $this->lastRotationTime = time();

        if($destination !== null) {
            $this->setDestination($destination);
        }

        if($mode !== null) {
            $this->setWriteMode($mode); //It calls $this->createFileHandler(); internally

        } else {
            $this->createFileHandler();
        }
    }

    public function __destruct() {
        if($this->config["blocking"]) {
            return;
        }

        while(!empty($this->asyncBuffer)) {
            $this->flush();
        }
    }

    /**
     * {@inheritdoc}
     * @todo Docbug - $context["exception"] aren't detected as Exception instance
     */
    public function log($level, $message, array $context = array())
    {

        $level = strtoupper($level);

        if (isset($this->config["levelsPriorities"][$level]) && $this->config["levelsPriorities"][$level] > $this->config["maximumLogLevel"]) {
            return;
        }

        $time = time();
        if ($this->config["rotateEnabled"] &&
            ($time - $this->lastRotationTime) > $this->config["rotationInterval"]) {
            $this->rotate();
        }

        $contextText = "";
        if (!empty($context) && is_array($context)) {
            $contextText = print_r($context, true);
        }

        $message = sprintf($this->config["lineFormat"],
            date($this->config["datetimeFormat"]), //%1$s - date
            $level, //%2$s - log level
            $message, //%3$s - text
            $contextText, //%4$s - context
            time() //%d - unix timestamp
        );

        if($this->config["blocking"]) {
            fwrite($this->destinationHandler, $message);

        } else {
            $this->asyncBuffer .= $message;
            $wrote = fwrite($this->destinationHandler, $this->asyncBuffer);
            $this->asyncBuffer = substr($this->asyncBuffer, $wrote);
        }
    }

    /**
     * Handles any custom log level you can imagine, even if it's paranoia level, just call
     * $shoutInstance->paranoia('Aaaa!!!')
     *
     * @param $level
     * @param array $arguments
     *
     * @throws InvalidArgumentException
     */
    public function __call($level, $arguments)
    {
        $message = (isset($arguments[0])) ? $arguments[0] : "";
        $context = (isset($arguments[1])) ? $arguments[1] : array();

        $this->log($level, $message, $context);
    }

    /**
     * Rotate log file
     *
     * @param bool $resetTimer
     * @throws RuntimeException Thrown if new file cannot be opened for write
     */
    public function rotate($resetTimer=true)
    {
        if(!$this->config["blocking"]) {
            while(!empty($this->asyncBuffer)) {
                $this->flush();
            }
        }

        if($resetTimer) {
            $this->lastRotationTime = time();
        }

        $this->log(self::INFO, "Rotating log file...");
        fclose($this->destinationHandler);
        $this->createFileHandler();
    }


    /**
     * Forces pushing remaining data from buffer to destination.
     * This method only makes sense in non-blocking mode.
     *
     * @return bool
     */
    public function flush() {
        if(!$this->config["blocking"] && !empty($this->asyncBuffer)) {
            $wrote = fwrite($this->destinationHandler, $this->asyncBuffer);
            $this->asyncBuffer = substr($this->asyncBuffer, $wrote);
        }

        return empty($this->asyncBuffer);
    }

    /**
     * Specifies log destination, you can use any valid file path or stream location supports writting.
     * You can also use %1$s for current unix timestamp or %2$s for current date (formated according to datetimeFormat).
     * This method will cause file to be re-opened.
     *
     * @param string $destination
     */
    public function setDestination($destination) {
        $this->config["destination"] = $destination;
        $this->createFileHandler();
    }

    /**
     * This method accepts any valid fopen() mode which allows writting.
     * Using this method will re-open file.
     *
     * @param string $mode Any valid fopen() mode allowing writting
     *
     * @throws InvalidArgumentException Invalid mode
     */
    public function setWriteMode($mode) {
        if(!in_array($mode, $this->validWriteModes)) {
            throw new InvalidArgumentException("Invalid write mode specified: $mode");
        }

        $this->config["writeMode"] = $mode;
        $this->createFileHandler();
    }

    /**
     * Log destination can be opened either in blocking or non-blocking mode. Blocking mode is mostly faster, but
     * sometimes can lock whole app until full log message is written. Using non-blocking mode have another drawback -
     * after sending log message to Shout it's internally buffered and than pushed to destination, if only part of
     * buffer can be written next attempt will be made on next message or after calling flush().
     *
     * @param bool $blocking
     * @see flush()
     */
    public function setBlocking($blocking) {
        $this->config["blocking"] = (bool)$blocking;

        if(!$this->config["blocking"]) {
            while(!empty($this->asyncBuffer)) {
                $this->flush();
            }
        }
    }

    /**
     * Disable/enable automatic log rotation (based on RotateInterval).
     *
     * @param bool $rotate
     */
    public function setRotate($rotate) {
        $this->config["rotateEnabled"] = (bool)$rotate;
    }

    /**
     * Defines how often, in seconds, rotation occurs.
     *
     * @param integer $interval
     *
     * @throws InvalidArgumentException Non-integer interval value
     */
    public function setRotateInerval($interval) {
        if(!is_integer($interval)) {
            throw new InvalidArgumentException("Interval should be integer");
        }

        $this->config["rotationInterval"] = $interval;
    }

    /**
     * Specifies how log line should look.
     * There are 6 modifiers:
     *  %1$s - date
     *  %2$s - log level (uppercased)
     *  %3$s - message text
     *  %4$s - context (formatted by print_r())
     *  %5$s - exception (formatted by print_r())
     *  %d - unix timestamp
     *
     * @param string $format
     * @see print_r()
     */
    public function setLineFormat($format) {
        $this->config["lineFormat"] = $format;
    }


    /**
     * Accepts any date() compilant format.
     *
     * @param $format
     * @see date()
     */
    public function setDatetimeFormat($format) {
        $this->config["datetimeFormat"] = $format;
    }


    /**
     * Every log level contains it's numeric value (default ones are defined by `Table 2. Syslog Message Severities` of
     * RFC 5424.
     * This method allows to specify maximum log level delivered to destination, eg. if you set it to 1 only ALERT and
     * EMERGENCY message will pass.
     *
     * @param number $level Any numeric value
     *
     * @throws InvalidArgumentException Non-numeric level passed
     */
    public function setMaximumLogLevel($level) {
        if(!is_numeric($level)) {
            throw new InvalidArgumentException("Maximum log level must be a number");
        }

        $this->config["maximumLogLevel"] = $level;
    }


    /**
     * Defines log level priority
     * Note: method doesn't prevent you from changing built-in log level, however it's not recomended
     *
     * @param string $level
     * @param number $priority
     */
    public function setLevelPriority($level, $priority)
    {
        $level = strtoupper($level);
        $this->config["levelsPriorities"][$level] = $priority;
    }

    /**
     * Creates file handler to handle log writes
     *
     * @throws RuntimeException Thrown if file cannot be opened for write
     */
    private function createFileHandler()
    {
        $path = sprintf($this->config["destination"],
                        time(),
                        date($this->config["datetimeFormat"])
                       );

        $this->destinationHandler = fopen($path, $this->config["writeMode"]);
        if(!$this->config["blocking"]) {
            stream_set_blocking($this->destinationHandler, (int)$this->config["blocking"]);
        }

        if (!$this->destinationHandler) {
            throw new RuntimeException("Failed to open file $path created from " .$this->config["destination"]. " expression (mode: " . $this->config["writeMode"] . ")");
        }
    }
}