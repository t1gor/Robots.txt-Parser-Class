<?php

/**
 * @group disallow-all
 */
class ApplyToAll extends \PHPUnit_Framework_TestCase
{
	/**
	 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/22
	 */
	public function testDisallowWildcard()
	{
		// init parser
		$parser = new RobotsTxtParser("
			User-Agent: *
			Disallow: /
		");
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		// asserts
		$this->assertTrue($parser->isDisallowed("/index"));
		$this->assertFalse($parser->isAllowed("/index"));
	}

	/**
	 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/22
	 */
	public function testAllowWildcard()
	{
		// init parser
		$parser = new RobotsTxtParser("
			User-agent: *
			Allow: /
		");
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		// asserts
		$this->assertFalse($parser->isDisallowed("/index"));
		$this->assertFalse($parser->isDisallowed("/"));
		$this->assertTrue($parser->isAllowed("/index"));
		$this->assertTrue($parser->isAllowed("/"));
	}
}
