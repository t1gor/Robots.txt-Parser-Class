<?php

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class RemoveDuplicateSitemapsTest extends TestCase
{
	/**
	 * @dataProvider generateDataForTest
	 * @cover RobotsTxtParser::isAllowed
	 * @cover RobotsTxtParser::isDisallowed
	 * @param string $robotsTxtContent
	 */
	public function testRemoveDuplicateSitemaps($robotsTxtContent)
	{
		$this->markTestSkipped('@TODO');

		$parser = new RobotsTxtParser($robotsTxtContent);
		// Check if the number of sitemaps is 5
		$this->assertTrue(count($parser->getSitemaps()) == 5);
	}

	/**
	 * Generate test case data
	 *
	 * @return array
	 */
	public function generateDataForTest()
	{
		return array(
			array("
				Sitemap: http://example.com/sitemap.xml?year=2015
				Sitemap: http://example.com/sitemap.xml?year=2015
				Sitemap: http://example.com/sitemap.xml?year=2015

				User-agent: *
				Disallow: /admin/
				Sitemap: http://somesite.com/sitemap.xml

				User-agent: Googlebot
				Sitemap: http://internet.com/sitemap.xml

				User-agent: Yahoo
				Sitemap: http://worldwideweb.com/sitemap.xml
				Sitemap: http://example.com/sitemap.xml?year=2016
			")
		);
	}
}
