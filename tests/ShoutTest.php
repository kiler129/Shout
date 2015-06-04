<?php
namespace noFlash\Shout;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class ShoutTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var vfsStreamDirectory
     */
    private $logRoot;

    public function setUp()
    {
        $this->logRoot = vfsStream::setup('log');
    }

    public function validWriteModesProvider()
    {
        return array(
            array('r+'),
            array('w'),
            array('w+'),
            array('a'),
            array('a+'),
            array('x'),
            array('x+'),
            array('c'),
            array('c+')
        );
    }

    /**
     * Testing x & x+ mode using vfs is impossible.
     *
     * @return array
     * @uses
     */
    public function vfsTestableWriteModesProvider()
    {
        return array(
            array('r+'),
            array('w'),
            array('w+'),
            array('a'),
            array('a+'),
            array('c'),
            array('c+')
        );
    }

    public function invalidWriteModesProvider()
    {
        return array(
            array('r'),
            array(''),
            array(null),
            array(false),
            array(true),
            array('test')
        );
    }

    public function standardLogLevelsProvider()
    {
        return array(
            array('EMERGENCY', 'EMERG'),
            array('ALERT', 'ALERT'),
            array('CRITICAL', 'CRITICAL'),
            array('ERROR', 'ERROR'),
            array('WARNING', 'WARN'),
            array('NOTICE', 'NOTICE'),
            array('INFO', 'INFO'),
            array('DEBUG', 'DEBUG')
        );
    }

    public function fileModesShortcutsProvider()
    {
        return array(
            array('FILE_OVERWRITE', 'w'),
            array('FILE_APPEND', 'a')
        );
    }

    /**
     * @dataProvider validWriteModesProvider
     */
    public function testValidWriteModeValidation($mode)
    {
        $shout = new Shout();

        $shoutReflection = new \ReflectionObject($shout);
        $config = $shoutReflection->getProperty('config');
        $config->setAccessible(true);

        $shout->setWriteMode($mode);

        $config = $config = $config->getValue($shout);

        $this->assertEquals($mode, $config['writeMode'], "Failed for mode: $mode");
    }

    /**
     * @dataProvider invalidWriteModesProvider
     */
    public function testInvalidWriteModeValidation($mode)
    {
        $shout = new Shout();
        $this->setExpectedException('\Psr\Log\InvalidArgumentException');
        $shout->setWriteMode($mode);
    }

    /**
     * @dataProvider vfsTestableWriteModesProvider
     */
    public function testWriteModeIsSetCorrectly($mode)
    {
        $logFile = vfsStream::url('log/test.log');
        $shout = new Shout($logFile);

        $shoutReflection = new \ReflectionObject($shout);
        $destinationHandler = $shoutReflection->getProperty('destinationHandler');
        $destinationHandler->setAccessible(true);

        $shout->setWriteMode($mode);

        $streamMeta = stream_get_meta_data($destinationHandler->getValue($shout));
        $this->assertEquals($mode, $streamMeta['mode'], "Failed to set for $mode");
    }

    /**
     * @dataProvider vfsTestableWriteModesProvider
     */
    public function testConstructorSetsValidFileMode($mode)
    {
        $shout = new Shout(null, $mode);

        $shoutReflection = new \ReflectionObject($shout);
        $destinationHandler = $shoutReflection->getProperty('destinationHandler');
        $destinationHandler->setAccessible(true);

        $shout->setWriteMode($mode);

        $streamMeta = stream_get_meta_data($destinationHandler->getValue($shout));
        $this->assertEquals($mode, $streamMeta['mode'], "Failed to set for $mode");
    }

    /**
     * @dataProvider fileModesShortcutsProvider
     */
    public function testClassProvidesFileModeShortcutsWithProperValues($constantName, $constantValue)
    {
        $constantName = '\noFlash\Shout\Shout::' . $constantName;

        $this->assertTrue(defined($constantName), "Constant $constantName not found");
        $this->assertEquals(constant($constantName), $constantValue);
    }

    public function testConstructorCreatesLogFile()
    {
        $logFile = vfsStream::url('log/test.log');
        new Shout($logFile, 'w');

        $this->assertTrue(file_exists($logFile));
    }

    public function testConstructorThrowsRuntimeExceptionOnInvalidFilePath()
    {
        $this->setExpectedExceptionRegExp('\RuntimeException', '/^Failed to open file/');
        new Shout(vfsStream::url('unknown file path'));
    }

    public function testFileCreationWithTimestamp()
    {
        $logFile = 'vfs://log/%d';
        new Shout($logFile);

        $files = $this->logRoot->getChildren();

        $this->assertCount(1, $files, "Created more than one file");

        $actualLogFilename = reset($files)->getName();
        $this->assertEquals(time(), $actualLogFilename, 'Invalid file created', 1);
    }

    public function testFileCreationWithDate()
    {
        $logFile = 'vfs://log/%2$s';
        $shout = new Shout();

        $shout->setDatetimeFormat('d.m.Y');
        $shout->setDestination($logFile);


        $files = $this->logRoot->getChildren();

        $actualLogFilename = reset($files)->getName();
        $this->assertEquals(date('d.m.Y'), $actualLogFilename, 'Invalid file created');
    }

    public function testSettingWriteModeRecreatesFileHandler()
    {
        $logFile = vfsStream::url('log/test.log');
        $shout = new Shout($logFile, 'w');
        unlink($logFile); //It's created by constructor

        $this->assertFalse(file_exists($logFile), 'Log exists before setting write mode');
        $shout->setWriteMode('w+');
        $this->assertTrue(file_exists($logFile));
    }

    /**
     * @dataProvider standardLogLevelsProvider
     */
    public function testClassProvidesAllStandardLogLevels($constantName, $constantValue)
    {
        $constantName = '\noFlash\Shout\Shout::' . $constantName;

        $this->assertTrue(defined($constantName), "Constant $constantName not found");
        $this->assertEquals(constant($constantName), $constantValue);
    }

    public function testLogLineProvidesCorrectDate()
    {
        $shout = new Shout(vfsStream::url('log/date.log'));

        $shout->setDatetimeFormat('d.m.Y');
        $shout->setLineFormat('%1$s');

        $shout->log('', '');
        $this->assertEquals(date('d.m.Y'), $this->logRoot->getChild('date.log')->getContent());
    }

    public function testLogLineProvidesLogLevel()
    {
        $logFilename = 'level.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath, Shout::FILE_OVERWRITE);
        $shout->setLineFormat('%2$s');

        //Standard log level using log() method
        $shout->log(Shout::INFO, '');
        $this->assertEquals(Shout::INFO, $this->logRoot->getChild($logFilename)->getContent(),
            'log() failed with INFO constant');
    }

    public function testLogLineConvertsLogLevelToUppercase()
    {
        $logFilename = 'levelUppercase.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath, Shout::FILE_OVERWRITE);
        $shout->setLineFormat('%2$s');

        //Standard log level using log() method
        $shout->log('iNfO', '');
        $this->assertEquals(Shout::INFO, $this->logRoot->getChild($logFilename)->getContent(),
            'Standard with log() method');

        //Standard log level using magic method
        $shout->setDestination($logFilePath); //Recreate fresh log
        $shout->info('');
        $this->assertEquals('INFO', $this->logRoot->getChild($logFilename)->getContent(), 'Standard with magic method');

        //Custom log level using log() method
        $shout->setDestination($logFilePath); //Recreate fresh log
        $shout->log('cUsToM', '');
        $this->assertEquals('CUSTOM', $this->logRoot->getChild($logFilename)->getContent(), 'Custom with log() method');

        //Custom log level using magic method
        $shout->setDestination($logFilePath); //Recreate fresh log
        $shout->cUsToM('');
        $this->assertEquals('CUSTOM', $this->logRoot->getChild($logFilename)->getContent(), 'Custom with magic method');
    }

    public function logMessagesProvider()
    {
        return array(
            array('Simple test'),
            array("New\nline"),
            array('UTF: ☃')
        );
    }

    /**
     * @dataProvider logMessagesProvider
     */
    public function testLogLineProvidesLogMessage($message)
    {
        $logFilename = 'message.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath);
        $shout->setLineFormat('%3$s');

        $shout->log('', $message);
        $this->assertSame($message, $this->logRoot->getChild($logFilename)->getContent());
    }

    public function testLogLineContextRepresentation()
    {
        $logFilename = 'context.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath);
        $shout->setLineFormat('%4$s');

        $context = array(
            'test1' => array('test2', 'test3'),
            null
        );

        $shout->log('', '', $context);
        $this->assertSame(print_r($context, true), $this->logRoot->getChild($logFilename)->getContent());
    }

    public function standardLogLevelsPrioritiesProvider()
    {
        /* These levels should be available inside Shout class
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7
        */

        return array(
            array(Shout::EMERGENCY, 999, true),
            array(Shout::EMERGENCY, 0, true),
            array(Shout::EMERGENCY, -1, false),
            array(Shout::ALERT, 999, true),
            array(Shout::ALERT, 0, false),
            array(Shout::ALERT, 1, true),
        );
    }

    /**
     * @dataProvider standardLogLevelsPrioritiesProvider
     */
    public function testLogLevelLimitForStandardLevels($messageType, $limitLevel, $shouldPrint)
    {
        $messageText = 'test';
        $logFilename = 'level_limit.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath);
        $shout->setLineFormat('%3$s');
        $shout->setMaximumLogLevel($limitLevel);

        $shout->log($messageType, $messageText);

        $this->assertSame($shouldPrint, ($this->logRoot->getChild($logFilename)->getContent() === $messageText));
    }

    public function testNonNumericLogLevelLimitValueTriggersException()
    {
        $logFilename = 'level_limit.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath);

        $this->setExpectedException('\Psr\Log\InvalidArgumentException', 'Maximum log level must be a number');
        $shout->setMaximumLogLevel('test');
    }

    public function testNullLogLevelLimitValueDisablesLogLimit()
    {
        $messageText = 'test';
        $logFilename = 'level_limit_disable.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath);
        $shout->setLineFormat('%3$s');

        $shout->setMaximumLogLevel(-PHP_INT_MAX);
        $shout->debug($messageText);

        $shout->setMaximumLogLevel(null);
        $shout->debug($messageText);

        $this->assertSame($messageText, $this->logRoot->getChild($logFilename)->getContent());
    }

    public function testCustomMessageWithNoLevelSetPrintsEvenIfLimitIsSet()
    {
        $messageText = 'test';
        $logFilename = 'level_limit_disable_on_custom.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath);
        $shout->setLineFormat('%3$s');

        $shout->setMaximumLogLevel(-PHP_INT_MAX);
        $shout->custom($messageText);

        $this->assertSame($messageText, $this->logRoot->getChild($logFilename)->getContent());
    }

    public function testMaximumLogLevelLimitIgnoresCustomLogLevelMessagesRegardingToItsPriority()
    {
        $logFilename = 'custom_log_level_prioirty.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath, 'w');
        $shout->setLineFormat('%3$s');

        $shout->setMaximumLogLevel(10);
        $shout->custom('1'); //It should print
        $this->assertSame('1', $this->logRoot->getChild($logFilename)->getContent(),
            'Custom message is NOT printed w/o setting level');

        $shout->setLevelPriority('custom', 20);
        $shout->custom('2'); //That should not print - maximum level set to 10 and "CUSTOM" is now level 20
        $this->assertSame('1', $this->logRoot->getChild($logFilename)->getContent(),
            'Custom message ignores level set');

        $shout->setMaximumLogLevel(30);
        $shout->custom('3'); //Changed maximum level to 30 - it should print
        $this->assertSame('13', $this->logRoot->getChild($logFilename)->getContent(),
            'Custom message is NOT printed after changing maximum level');

        $shout->setLevelPriority('custom', 50);
        $shout->custom('4'); //New level for custom is 50, maximum is set to 30 - it should not print
        $this->assertSame('13', $this->logRoot->getChild($logFilename)->getContent(),
            'Custom message is printed after changing it\'s level');
    }

    public function testUsingManualRotationWithStaticFileRecreatesClearsFileUsed()
    {
        $logFilename = 'static_file_rotate.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath, Shout::FILE_OVERWRITE);
        $shout->setLineFormat('%3$s');

        $shout->info('before rotation');
        $shout->rotate();
        $shout->info('after rotation');

        $this->assertSame('after rotation', $this->logRoot->getChild($logFilename)->getContent());
    }

    public function testUsingManualRotationWithDynamicFileCreatesNewFile()
    {
        $logFilePath = vfsStream::url('log/%d');

        $shout = new Shout($logFilePath, Shout::FILE_OVERWRITE);
        $shout->setLineFormat('%3$s');

        $shout->info('before rotation');
        sleep(1); //File is created with name based on time
        $shout->rotate();
        $shout->info('after rotation');

        $files = $this->logRoot->getChildren();
        $this->assertCount(2, $files, 'Invalid number of files (should be two)');

        $this->assertContains('before rotation', reset($files)->getContent(),
            'File before rotation doesn\'t have expected content');
        $this->assertContains('after rotation', end($files)->getContent(),
            'File after rotation doesn\'t have expected content');
    }

    public function testAutomaticLogRotationWithStaticFile()
    {
        $logFilename = 'static_file_auto_rotate.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath, Shout::FILE_OVERWRITE);
        $shout->setLineFormat('%3$s');
        $shout->setRotateInerval(2);
        $shout->setRotate(true);

        $shout->info('1');
        $shout->debug('2');

        sleep(3);

        $shout->info('3');
        $shout->debug('4');

        $this->assertSame('34', $this->logRoot->getChild($logFilename)->getContent());
    }

    public function testAutomaticRotationWithDynamicFile()
    {
        $logFilePath = vfsStream::url('log/%d');

        $shout = new Shout($logFilePath, Shout::FILE_OVERWRITE);
        $shout->setLineFormat('%3$s');
        $shout->setRotateInerval(2);
        $shout->setRotate(true);

        $shout->info('1');
        $shout->debug('2');

        sleep(3);

        $shout->info('3');
        $shout->debug('4');

        $files = $this->logRoot->getChildren();
        $this->assertCount(2, $files, 'Invalid number of files (should be two)');

        $this->assertContains('12', reset($files)->getContent(), 'File before rotation doesn\'t have expected content');
        $this->assertContains('34', end($files)->getContent(), 'File after rotation doesn\'t have expected content');
    }

    public function testManualLogRotationResetsAutomaticRotationTimerByDefault()
    {
        $logFilePath = vfsStream::url('log/%d');

        $shout = new Shout($logFilePath, Shout::FILE_OVERWRITE);
        $shout->setLineFormat('%3$s');
        $shout->setRotateInerval(5);
        $shout->setRotate(true);

        sleep(3);
        $shout->info('1'); //1st log file
        sleep(2);
        $shout->rotate();
        $shout->debug('2'); //2nd log file
        sleep(1);
        //3+2+1=6s elapsed since start, log was rotated - if time wasn't reset since then it will trigger next rotation and end up in 3rd log file
        $shout->debug('3'); //...but it should in 2nd!

        $files = $this->logRoot->getChildren();
        $this->assertCount(2, $files, 'Invalid number of files created');

        reset($files);
        $this->assertSame('23', next($files)->getContent(), 'Wrong 2nd log file contents');
    }
}
