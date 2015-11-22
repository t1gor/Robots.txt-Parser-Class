<?php

class MoreRules extends \PHPUnit_Framework_TestCase
{
	/**
	 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/22
	 */
	public function testDisallowRules()
	{
		// init parser
		$parser = new RobotsTxtParser("
			User-Agent: *
			Disallow: /foo
			Disallow: /bar
			Allow: /efg
		");

		// asserts
		$this->assertTrue($parser->isDisallowed("/foo"));
		$this->assertTrue($parser->isDisallowed("/bar"));
		$this->assertFalse($parser->isDisallowed("/efg"));
		$this->assertFalse($parser->isAllowed("/foo"));
		$this->assertFalse($parser->isAllowed("/bar"));
		$this->assertTrue($parser->isAllowed("/efg"));
	}

	/**
	 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/22
	 */
	public function testAllowRules()
	{
		// init parser
		$parser = new RobotsTxtParser("
			User-agent: *
			Allow: /foo
			Allow: /bar
			Disallow: /efg
		");

		// asserts
		$this->assertTrue($parser->isAllowed("/foo"));
		$this->assertTrue($parser->isAllowed("/bar"));
		$this->assertFalse($parser->isAllowed("/efg"));
		$this->assertFalse($parser->isDisallowed("/foo"));
		$this->assertFalse($parser->isDisallowed("/bar"));
		$this->assertTrue($parser->isDisallowed("/efg"));
	}
}
