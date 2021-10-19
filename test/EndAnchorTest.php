<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::isDisallowed
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::isAllowed
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::checkRules
 */
class EndAnchorTest extends TestCase {

	/**
	 * @dataProvider generateDataForTest
	 * @param string $path
	 * @param string $robotsTxtContent
	 * @param bool   $assertAllowed
	 */
	public function testEndAnchor(string $path, string $robotsTxtContent, bool $assertAllowed) {
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);

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
	public function generateDataForTest() {
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
				/**
				 * @see InvalidPathTest for details why this is changed
				 */
				true,
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
