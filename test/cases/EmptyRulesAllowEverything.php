<?php

class EmptyRulesAllowEverything extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @covder RobotsTxtParser::isAllowed
     * @covder RobotsTxtParser::isDisallowed
     * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/23
     */
    public function testEmptyRulesAllow($robotsTxtContent)
    {
        $parser = new RobotsTxtParser($robotsTxtContent);
        $this->assertInstanceOf('RobotsTxtParser', $parser);
        $this->assertTrue($parser->isAllowed('/foo'));
        $this->assertfalse($parser->isDisallowed('/foo'));
    }

    /**
     * Generate test case data
     *
     * @return array
     */
    public function generateDataForTest()
    {
        return array(
            array("Sitemap: http://www.example.com/sitemap.xml")
        );
    }
}
