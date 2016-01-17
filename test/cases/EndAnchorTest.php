<?php

class EndAnchorTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider generateDataForTest
	 * @covers       RobotsTxtParser::isAllowed
	 * @covers       RobotsTxtParser::isDisallowed
	 * @covers       RobotsTxtParser::checkRules
	 * @param string $robotsTxtContent
	 */
	public function testEndAnchor($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);

		$this->assertTrue($parser->isAllowed("/"));
		$this->assertTrue($parser->isDisallowed("/asd"));
		$this->assertTrue($parser->isDisallowed("/asd/"));
		$this->assertFalse($parser->isDisallowed("/"));
		$this->assertFalse($parser->isAllowed("/asd"));
		$this->assertFalse($parser->isAllowed("/asd/"));
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
				Disallow: /*
				Allow: /$
			")
		);
	}
}
