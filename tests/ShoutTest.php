<?php
namespace noFlash\Shout;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class ShoutTest extends \PHPUnit_Framework_TestCase {

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
            array('ALERT',  'ALERT'),
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
     * @dataProvider fileModesShortcutsProvider
     */
    public function testClassProvidesFileModeShortcutsWithProperValues($constantName, $constantValue)
    {
        $constantName = '\noFlash\Shout\Shout::'.$constantName;

        $this->assertTrue(defined($constantName), "Constant $constantName not found");
        $this->assertEquals(constant($constantName), $constantValue);
    }

    public function testConstructorCreatesLogFile()
    {
        $logFile = vfsStream::url('log/test.log');
        new Shout($logFile, 'w');

        $this->assertTrue(file_exists($logFile));
    }

    public function testFileCreationWithTimestamp()
    {
        $logFile = 'vfs://log/%d';
        new Shout($logFile);

        $files = $this->logRoot->getChildren();
        $numberOfFiles = count($files);

        $this->assertSame(1, $numberOfFiles, "Created $numberOfFiles - expected exactly one");

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
        $constantName = '\noFlash\Shout\Shout::'.$constantName;

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
        $this->assertEquals(Shout::INFO, $this->logRoot->getChild($logFilename)->getContent(), 'log() failed with INFO constant');
    }

    public function testLogLineConvertsLogLevelToUppercase()
    {
        $logFilename = 'levelUppercase.log';
        $logFilePath = vfsStream::url('log/' . $logFilename);

        $shout = new Shout($logFilePath, Shout::FILE_OVERWRITE);
        $shout->setLineFormat('%2$s');

        //Standard log level using log() method
        $shout->log('iNfO', '');
        $this->assertEquals(Shout::INFO, $this->logRoot->getChild($logFilename)->getContent(), 'Standard with log() method');

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


}
