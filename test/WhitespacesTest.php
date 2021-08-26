<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class WhitespacesTest extends TestCase {

	/**
	 * @dataProvider generateDataForTest
	 * @covers       RobotsTxtParser::isDisallowed
	 * @covers       RobotsTxtParser::checkRules
	 *
	 * @param string $robotsTxtContent
	 */
	public function testWhitespaces($robotsTxtContent) {
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$rules = $parser->getRules('*');

		$this->assertNotEmpty($rules, 'expected rules for *');
		$this->assertArrayHasKey('disallow', $rules);
		$this->assertNotEmpty($rules['disallow'], 'disallow failed');
		$this->assertArrayHasKey('allow', $rules);
		$this->assertNotEmpty($rules['allow'], 'allow failed');
	}

	/**
	 * Generate test case data
	 * @return array
	 */
	public function generateDataForTest() {
		return [
			[
				"
					User-agent: *
					Disallow : /admin
					Allow    :   /admin/front
				",
			],
		];
	}
}
