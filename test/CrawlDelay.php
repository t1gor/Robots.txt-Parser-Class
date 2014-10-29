<?php
	/**
	 * @backupGlobals disabled
	 */
	class CrawlDelayTest extends \PHPUnit_Framework_TestCase
	{
		/**
		 * Load library
		 */
		public static function setUpBeforeClass()
		{
			require_once(realpath(__DIR__.'/../robotstxtparser.php'));
		}

		/**
		 * @dataProvider generateDataForTest
		 * @covers RobotsTxtParser::isDisallowed
		 * @covers RobotsTxtParser::checkRule
		 * @param string $robotsTxtContent
		 */
		public function testCrawlDelay($robotsTxtContent)
		{
			// init parser
			$parser = new RobotsTxtParser($robotsTxtContent);
			$this->assertInstanceOf('RobotsTxtParser', $parser);
			$this->assertObjectHasAttribute('rules', $parser);
			$this->assertArrayHasKey('ahrefsbot', $parser->rules);
			$this->assertArrayHasKey('crawl-delay', $parser->rules['ahrefsbot']);
			$this->assertEquals(1.5, $parser->rules['ahrefsbot']['crawl-delay']);
		}

		/**
		 * Generate test case data
		 * @return array
		 */
		public function generateDataForTest()
		{
			return array(
				array("
					User-Agent: AhrefsBot
					Crawl-Delay: 1.5
				")
			);
		}
	}
?>