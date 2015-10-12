<?php

/**
 * Note: Test-data may become outdated, and the test will most likely fail when issue #22 is addressed.
 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/22
 */
class AllowDisallowException extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @covers       RobotsTxtParser::isDisallowed
     * @param string $robotsTxtContent
     */
    public function testIsAllowedException($robotsTxtContent)
    {
        // init parser
        $parser = new RobotsTxtParser($robotsTxtContent);
        $this->assertInstanceOf('RobotsTxtParser', $parser);
        try {
            $result = $parser->isAllowed("/");
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertEquals($errmsg, 'Unable to check rules');
    }

    /**
     * @dataProvider generateDataForTest
     * @covers       RobotsTxtParser::isDisallowed
     * @param string $robotsTxtContent
     */
    public function testIsDisallowedException($robotsTxtContent)
    {
        // init parser
        $parser = new RobotsTxtParser($robotsTxtContent);
        $this->assertInstanceOf('RobotsTxtParser', $parser);
        try {
            $result = $parser->isDisallowed("/");
        } catch (Exception $e) {
            $errmsg = $e->getMessage();
        }
        $this->assertEquals($errmsg, 'Unable to check rules');
    }

    /**
     * Generate test case data
     *
     * @return array
     */
    public function generateDataForTest()
    {
        return array(
            array("
					User-Agent: *
					Disallow: /admin/
				")
        );
    }
}
