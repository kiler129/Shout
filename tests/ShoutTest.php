<?php
namespace noFlash\Shout;

use org\bovigo\vfs\vfsStream;

class ShoutTest extends \PHPUnit_Framework_TestCase {

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
     */
    public function testableWriteModesProvider()
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
     * @dataProvider testableWriteModesProvider
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

    public function testConstructorCreatesLogFile()
    {
        $logFile = vfsStream::url('log/test.log');
        new Shout($logFile, 'w');

        $this->assertEquals(true, file_exists($logFile));
    }

    public function testSettingWriteModeRecreatesFileHandler()
    {
        $logFile = vfsStream::url('log/test.log');
        $shout = new Shout($logFile, 'w');
        unlink($logFile); //It's created by constructor

        $this->assertEquals(false, file_exists($logFile), 'Log exists before setting write mode');
        $shout->setWriteMode('w+');
        $this->assertEquals(true, file_exists($logFile));
    }
}
