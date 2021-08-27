<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class AtSymbolTest extends TestCase
	{
		/**
		 * @dataProvider generateDataForTest
		 * @covers RobotsTxtParser::isDisallowed
		 * @covers RobotsTxtParser::checkRules
		 * @param string $robotsTxtContent
		 */
		public function testContainingAtChar($robotsTxtContent)
		{
			$parser = new RobotsTxtParser($robotsTxtContent);
			$this->assertTrue($parser->isAllowed("/peanuts"));
			$this->assertFalse($parser->isDisallowed("/peanuts"));
			$this->assertFalse($parser->isAllowed("/url_containing_@_symbol"));
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
