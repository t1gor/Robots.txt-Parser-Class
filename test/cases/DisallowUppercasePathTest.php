<?php
class DisallowUppercasePathTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @covers RobotsTxtParser::isDisallowed
     * @covers RobotsTxtParser::checkRules
     * @param string $robotsTxtContent
     */
    public function testDisallowUppercasePath($robotsTxtContent)
    {
        // init parser
        $parser = new RobotsTxtParser($robotsTxtContent);
        $this->assertInstanceOf('RobotsTxtParser', $parser);
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
