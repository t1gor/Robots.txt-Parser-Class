<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::isDisallowed
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::isAllowed
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::checkRules
 */
class DisallowUppercasePathTest extends TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @param string $robotsTxtContent
     */
    public function testDisallowUppercasePath(string $robotsTxtContent)
    {
        // init parser
        $parser = new RobotsTxtParser($robotsTxtContent);
        $this->assertTrue($parser->isDisallowed("/Admin"));
		$this->assertFalse($parser->isAllowed("/Admin"));
    }

    /**
     * Generate test case data
     * @return array
     */
    public function generateDataForTest(): array {
        return [
            [
	            "
					User-agent: *
					Disallow : /Admin
				"
            ]
        ];
    }
}
