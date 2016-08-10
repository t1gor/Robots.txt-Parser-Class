<?php

class InvalidPathTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @param string $robotsTxtContent
     */
    public function testInvalidPathAllowed($robotsTxtContent)
    {
        /**
         * Test currently disabled due to issues
         * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/69
         */
        // init parser
        $parser = new RobotsTxtParser($robotsTxtContent);
        $this->assertInstanceOf('RobotsTxtParser', $parser);
        $this->assertFalse($parser->isAllowed('*wildcard'));
        $this->assertTrue($parser->isDisallowed("&&1@|"));
        $this->assertFalse($parser->isAllowed('+£€@@1¤'));

    }

    /**
     * Generate test case data
     * @return array
     */
    public function generateDataForTest()
    {
        return array(
            array(
                <<<ROBOTS
                User-agent: *
Disallow: /
ROBOTS
            )
        );
    }
}
