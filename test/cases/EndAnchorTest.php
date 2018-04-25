<?php

class EndAnchorTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider generateDataForTest
	 * @covers       RobotsTxtParser::isAllowed
	 * @covers       RobotsTxtParser::isDisallowed
	 * @covers       RobotsTxtParser::checkRules
	 * @param string $path
	 * @param string $robotsTxtContent
	 * @param bool   $assertAllowed
	 */
	public function testEndAnchor($path, $robotsTxtContent, $assertAllowed)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);

		if ($assertAllowed) {
			$this->assertTrue($parser->isAllowed($path));
			$this->assertFalse($parser->isDisallowed($path));
		} else {
			$this->assertTrue($parser->isDisallowed($path));
			$this->assertFalse($parser->isAllowed($path));
		}
	}

	/**
	 * Generate test case data
	 * @return array
	 */
	public function generateDataForTest()
	{
		// Data provider defined in format:
		// [tested path, robotsTxtContent, true when allowed / false when disallowed]
		return [
			[
				"/",
				"
					User-Agent: *
					Disallow: /*
					Allow: /$
				",
				true,
			],
			[
				"/asd",
				"
					User-Agent: *
					Disallow: /*
					Allow: /$
				",
				false,
			],
			[
				"/asd/",
				"
					User-Agent: *
					Disallow: /*
					Allow: /$
				",
				false,
			],
			[
				"/deny_all/",
				"
					User-Agent: *
					Disallow: *deny_all/$
				",
				false,
			],
			[
				"/deny_all/",
				"
					User-Agent: *
					Disallow: /deny_all/$
				",
				false,
			],
			[
				"/deny_all/",
				"
					User-Agent: *
					Disallow: deny_all/$
				",
				true,
			],
		];
	}
}
