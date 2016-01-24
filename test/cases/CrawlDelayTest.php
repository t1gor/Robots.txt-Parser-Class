<?php
	class CrawlDelayTest extends \PHPUnit_Framework_TestCase
	{
		/**
		 * @dataProvider generateDataForTest
		 * @param string $robotsTxtContent
		 */
		public function testCrawlDelay($robotsTxtContent)
		{
			// init parser
			$parser = new RobotsTxtParser($robotsTxtContent);
			$this->assertInstanceOf('RobotsTxtParser', $parser);
			$this->assertEquals(0, $parser->getDelay());
			$this->assertContains('crawl-delay directive: Not found', $parser->getLog());
			$this->assertEquals(0.9, $parser->getDelay('GoogleBot'));
			$this->assertEquals(1.5, $parser->getDelay('AhrefsBot'));
		}

		/**
		 * Generate test case data
		 * @return array
		 */
		public function generateDataForTest()
		{
			return array(
				array("
					User-Agent: GoogleBot
					Crawl-Delay: 0.9
					User-Agent: AhrefsBot
					Crawl-Delay: 1.5
				")
			);
		}
	}
