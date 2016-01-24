<?php

class CacheDelayTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider generateDataForTest
	 * @param string $robotsTxtContent
	 */
	public function testCacheDelay($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$this->assertEquals(0.5, $parser->getDelay('*', 'cache-delay'));
		$this->assertContains('cache-delay directive (unofficial): Not found, fallback to crawl-delay directive', $parser->getLog());
		$this->assertEquals(3.7, $parser->getDelay('GoogleBot', 'cache-delay'));
		$this->assertEquals(8, $parser->getDelay('AhrefsBot', 'cache-delay'));
	}

	/**
	 * Generate test case data
	 * @return array
	 */
	public function generateDataForTest()
	{
		return array(
			array(<<<ROBOTS
User-Agent: *
Crawl-Delay: 0.5

User-Agent: GoogleBot
Cache-Delay: 3.7

User-Agent: AhrefsBot
Cache-Delay: 8
ROBOTS
			)
		);
	}
}
