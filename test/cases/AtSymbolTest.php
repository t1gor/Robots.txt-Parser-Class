<?php
	class AtSymbolTest extends \PHPUnit_Framework_TestCase
	{
		/**
		 * @dataProvider generateDataForTest
		 * @covers RobotsTxtParser::isDisallowed
		 * @covers RobotsTxtParser::checkRule
		 * @param string $robotsTxtContent
		 */
		public function testContainingAtChar($robotsTxtContent)
		{
			// init parser
			$parser = new RobotsTxtParser($robotsTxtContent);
			$this->assertInstanceOf('RobotsTxtParser', $parser);
			$this->assertFalse($parser->isDisallowed("/peanuts"));
			$this->assertTrue($parser->isDisallowed("/url_containing_@_symbol"));
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
					Disallow: /url_containing_@_symbol
					Allow: /peanuts
				")
			);
		}
	}
