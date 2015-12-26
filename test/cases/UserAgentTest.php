<?php

class EmptyDisallowTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider generateDataForTest
	 * @covers       RobotsTxtParser::isAllowed
	 * @covers       RobotsTxtParser::isDisallowed
	 * @covers       RobotsTxtParser::checkRule
	 * @param string $robotsTxtContent
	 */
	public function testEmptyDisallow($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);

		$this->assertTrue($parser->isAllowed("/"));
		$this->assertTrue($parser->isAllowed("/article"));
		$this->assertTrue($parser->isDisallowed("/temp"));

		$this->assertTrue($parser->isAllowed("/temp", "spiderX/1.0"));
		$this->assertTrue($parser->isDisallowed("/assets", "spiderX/1.0"));
		$this->assertTrue($parser->isAllowed("/forum", "spiderX/1.0"));

		$this->assertTrue($parser->isDisallowed("/", "botY-test"));
		$this->assertTrue($parser->isAllowed("/forum/", "botY-test"));
		$this->assertTrue($parser->isDisallowed("/forum/topic", "botY-test"));
		$this->assertTrue($parser->isDisallowed("/public", "botY-test"));

		$this->assertTrue($parser->isAllowed("/", "crawlerZ"));
		$this->assertTrue($parser->isDisallowed("/forum", "crawlerZ"));
		$this->assertTrue($parser->isDisallowed("/public", "crawlerZ"));
	}

	/**
	 * Generate test case data
	 * @return array
	 */
	public function generateDataForTest()
	{
		return array(
			array("
				User-agent: *
				Disallow: /admin
				Disallow: /temp
				Disallow: /forum
				
				User-agent: spiderX
				Disallow:
				Disallow: /admin
				Disallow: /assets
				
				User-agent: botY
				Disallow: /
				Allow: /forum/$
				Allow: /article
				
				User-agent: crawlerZ
				Disallow:
				Disallow: /
				Allow: /$
			")
		);
	}
}
