<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::getRules
 */
class WhitespacesTest extends TestCase {

	/**
	 * @dataProvider generateDataForTest
	 * @param string $robotsTxtContent
	 */
	public function testWhitespaces(string $robotsTxtContent) {
		// init parser
		$parser = (new RobotsTxtParser())->setContent($robotsTxtContent);
		$rules = $parser->getRules('*');

		$this->assertNotEmpty($rules, 'expected rules for *');
		$this->assertArrayHasKey('disallow', $rules);
		$this->assertNotEmpty($rules['disallow'], 'disallow failed');
		$this->assertArrayHasKey('allow', $rules);
		$this->assertNotEmpty($rules['allow'], 'allow failed');
	}

	/**
	 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/93
	 */
	public function testNoSpaceAfterColon() {
		$parser = (new RobotsTxtParser())->setContent("user-agent:*\ndisallow:/\n");

		$this->assertFalse($parser->isAllowed('/'));
		$this->assertTrue($parser->isDisallowed('/'));
		$this->assertSame(['*' => ['disallow' => ['/']]], $parser->getRules());
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
			[
				"user-agent:*\ndisallow:/admin\nallow:/admin/front\n",
			],
		];
	}
}
