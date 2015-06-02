<?php
namespace noFlash\Shout;


class ShoutTest extends \PHPUnit_Framework_TestCase {

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
}
