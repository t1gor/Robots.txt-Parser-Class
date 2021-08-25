<?php

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class InternalEncodingTest extends TestCase
{
    public function testInternalEncoding()
    {
	    $this->markTestSkipped('@TODO');

        /**
         * Test currently disabled due to issues
         * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/71
         */
        $this->assertTrue(mb_internal_encoding('utf-8'));
        $parser = new RobotsTxtParser('', 'iso-8859-1');
        $this->assertEquals('UTF-8', mb_internal_encoding());
    }
}
