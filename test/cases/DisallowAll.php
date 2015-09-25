<?php

/**
 * @group disallow-all
 */
class DisallowAll extends \PHPUnit_Framework_TestCase
{
	/**
	 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/22
	 */
	public function testEmptyDisallow()
	{
		// init parser
		$parser = new RobotsTxtParser("
			User-Agent: *
			Disallow: /
		");

		// asserts
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$this->assertTrue($parser->isDisallowed("/index"));
		$this->assertFalse($parser->isAllowed("/index"));
	}
}
