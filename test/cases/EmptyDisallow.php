<?php

class EmptyDisallowTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider generateDataForTest
	 * @covers RobotsTxtParser::isDisallowed
	 * @covers RobotsTxtParser::checkRule
	 * @param string $robotsTxtContent
	 */
	public function testEmptyDisallow($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$this->assertTrue($parser->isDisallowed("/foo"));
		$this->assertFalse($parser->isDisallowed("/peanuts"));
	}

	/**
	 * Generate test case data
	 * @return array
	 */
	public function generateDataForTest()
	{
		return array(
			array("
				User-Agent: *
				Disallow:
				Disallow: /foo
				Disallow: /bar
			")
		);
	}
}
