<?php

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class DisallowUppercasePathTest extends TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @covers RobotsTxtParser::isDisallowed
     * @covers RobotsTxtParser::checkRules
     * @param string $robotsTxtContent
     */
    public function testDisallowUppercasePath($robotsTxtContent)
    {
	    $this->markTestSkipped('@TODO');

        // init parser
        $parser = new RobotsTxtParser($robotsTxtContent);
        $this->assertTrue($parser->isDisallowed("/Admin"));
		$this->assertFalse($parser->isAllowed("/Admin"));
    }

    /**
     * Generate test case data
     * @return array
     */
    public function generateDataForTest()
    {
        return array(
            array("
					User-agent: *
					Disallow : /Admin
				")
        );
    }
}
