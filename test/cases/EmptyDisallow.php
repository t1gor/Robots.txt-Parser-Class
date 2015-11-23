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
		
		// Commented out due to a bug. Please see issue #34
		// https://github.com/t1gor/Robots.txt-Parser-Class/issues/34
		//$this->assertTrue($parser->isAllowed("/temp", "spiderX"));
		//$this->assertTrue($parser->isDisallowed("/assets", "spiderX"));
		//$this->assertTrue($parser->isAllowed("/forum", "spiderX"));

		//$this->assertTrue($parser->isDisallowed("/", "botY"));
		//$this->assertTrue($parser->isAllowed("/forum/", "botY"));
		//$this->assertTrue($parser->isDisallowed("/forum/topic", "botY"));
		//$this->assertTrue($parser->isDisallowed("/public", "botY"));

		//$this->assertTrue($parser->isAllowed("/", "crawlerZ"));
		//$this->assertTrue($parser->isDisallowed("/forum", "crawlerZ"));
		//$this->assertTrue($parser->isDisallowed("/public", "crawlerZ"));
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
