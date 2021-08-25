<?php

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @group disallow-all
 */
class DisallowAllTest extends TestCase
{
	/**
	 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/22
	 */
	public function testDisallowWildcard()
	{
		$this->markTestSkipped('@TODO');

		// init parser
		$parser = new RobotsTxtParser("
			User-Agent: *
			Disallow: /
		");
		// asserts
		$this->assertTrue($parser->isDisallowed("/index"));
		$this->assertFalse($parser->isAllowed("/index"));
	}

	/**
	 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/22
	 */
	public function testAllowWildcard()
	{
		$this->markTestSkipped('@TODO');

		// init parser
		$parser = new RobotsTxtParser("
			User-agent: *
			Allow: /
		");
		// asserts
		$this->assertFalse($parser->isDisallowed("/index"));
		$this->assertFalse($parser->isDisallowed("/"));
		$this->assertTrue($parser->isAllowed("/index"));
		$this->assertTrue($parser->isAllowed("/"));
	}
}
