<?php

/**
 * @group unlisted-path
 */
class UnlistedPath extends \PHPUnit_Framework_TestCase
{
    /**
     * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/22
     */
    public function testAllowUnlistedPath()
    {
        // init parser
        $parser = new RobotsTxtParser("
			User-Agent: *
			Disallow: /admin/
		");
        $this->assertInstanceOf('RobotsTxtParser', $parser);
        // asserts
        $this->assertTrue($parser->isAllowed("/"));
        $this->assertFalse($parser->isDisallowed("/"));
    }
}
