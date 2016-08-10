<?php

class InternalEncodingTest extends \PHPUnit_Framework_TestCase
{
    public function testInternalEncoding()
    {
        /**
         * Test currently disabled due to issues
         * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/71
         */
        $this->assertTrue(mb_internal_encoding('utf-8'));
        $parser = new RobotsTxtParser('', 'iso-8859-1');
        $this->assertInstanceOf('RobotsTxtParser', $parser);
        $this->assertEquals('UTF-8', mb_internal_encoding());
    }
}
