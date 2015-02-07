# Shout!
Small, fast and PSR-3 compliant logging library.
Yes, it was created to blame you for every failure (which may not even be yours).

### Features
  * Logging to any files/streams (including php://stdout)
  * Customizable line format
  * [RFC 5424](http://tools.ietf.org/html/rfc5424) & user defined log levels
  * Limiting logging to maximum level

### Requirements
  * PHP >=5.3
  * [PSR-3 interfaces](https://github.com/php-fig/log)

### Installation
#### Using composer
Composer is recommended method of installation due to it's simplicity and automatic dependencies managment.

  0. You need composer of course - [installation takes less than a minute](https://getcomposer.org/download/)
  1. Run `php composer.phar require noflash/shout` in your favourite terminal to install Shout with dependencies
  2. Include `vendor/autoload.php` in your application source code
   
#### Manual
Details will be available soon.  
*Basically you need to download [PSR-3 interfaces](https://github.com/php-fig/log), put them in directory (eg. vendor) and include all files (or use PSR-4 compliant autoloader).*

### Usage
Most of the time you will use Shout with other libraries requiring [PSR-3](http://www.php-fig.org/psr/psr-3/) logger. Configuring logger to use with other project isn't different than using it in your own project.
Basic usage requires few lines:
```php
<?php
require_once("vendor/autoload.php");

use noFlash\Shout\Shout;
$logger = new Shout();
$logger->info("Hello world");
```
This example (also found in [examples directory](https://github.com/kiler129/Shout/tree/master/examples) as **BasicUsage.php**) will print `<01.01.2015 13:22:46> [INFO] Hello world!` with trailing new line (\n) to stdout.  
For more examples you should check various [examples](https://github.com/kiler129/Shout/tree/master/examples) - they're well commented.

Available methods:
  * **emergency/alert/...(message, context)** - Every log level have method named after it. So if you want to log "warning" just use `Shout->warning("Be warned!")`. Second argument can be array with any informations possible to represent as string by (formatted by [print_r()](http://php.net/print_r)).
  * **log(level, message, context)** - It have the same effect as methods described below, so calling `Shout->log("warning", "Be warned!")` produces the same result as example above.
  * **rotate()** - Performs manual log rotation. By default it also resets rotation timer, to prevent it pass `false` as first argument.
  * **flush()** - Tries to send remaining buffer in non-blocking mode. It will return `true` if buffer was written completely.

### Configuration
Shout comes preconfigured by default, but allows to configure almost anything. List below specifies configuration methods along with default values (specified in brackets). 
  * **setDestination(php://stdout)** - Specifies log destination, you can use any valid file path or stream location supports writing. You can also use %1$s for current unix timestamp or %2$s for current date (formatted according to datetimeFormat). Destination can also be specified using first argument passed to Shout class constructor.
  * **setWriteMode(Shout::FILE_APPEND)** - Opening new file Shout can either append to it (`Shout::FILE_APPEND`) or truncate and write from beginning (`Shout::FILE_OVERWRITE`). This method accepts any valid [fopen()](http://php.net/fopen) mode which allows writting. Using this method will re-open file. You can also pass mode as second constructor argument.
  * **setBlocking(true)** - Log destination can be opened either in blocking or non-blocking mode. Blocking mode is mostly faster, but sometimes can lock whole app until full log message is written. Using non-blocking mode have another drawback - after sending log message to Shout it's internally buffered and than pushed to destination, if only part of buffer can be written next attempt will be made on next message or after calling Shout->flush().
  * **setRotate(false)** - Disable/enable automatic log rotation (based on RotateInterval). Log rotation can be used even with destination without modifiers (but it only makes sense if WriteMode is set to Shout::FILE_OVERWRITE), but it's designed to be used destination set to eg. `/var/log/awesome_app_%d.log`
  * **setRotateInterval(86400)** - Defines how often, in seconds, rotation occurs. Be aware that Shout doesn't fire rotation unless new message is passed, so if you start logging at 00:00:00, set rotation to 1 hour, send first message at 00:59:59 and next at 03:00:05 log will rotate at 03:00:05 - no empty files will be created.
  * **setLineFormat(\<%1$s\> [%2$s] %3$s [%4$s] [%5$s]\n)** - How line should be formated. You can use 6 modifiers: 
    * %1$s - date
    * %2$s - log level (uppercased)
    * %3$s - message text
    * %4$s - context (formatted by [print_r()](http://php.net/print_r)) 
    * %5$s - unix timestamp
  * **setDatetimeFormat(d.m.Y H:i:s)** - It accepts any [date()](http://php.net/date) compliant format.
  * **setMaximumLogLevel(999)** - Every log level contains it's numeric value (default ones are defined by `Table 2. Syslog Message Severities` of [RFC 5424](http://tools.ietf.org/html/rfc5424)). This method allows to specify maximum log level delivered to destination, eg. if you set it to 1 only ALERT and EMERGENCY message will pass.
  * **setLevelPriority(level, value)** - In fact PSR-3 states custom log levels are forbidden, but I Shout supports them. By default messages with custom level don't have priority (so they're ignore MaximumLogLevel). This method allows setting priority (and even change builtin levels priority, which is NOT recommended).