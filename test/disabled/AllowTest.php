<?php

class AllowTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @param string $robotsTxtContent
     */
    public function testDisAllow($robotsTxtContent)
    {
        /**
         * Test currently disabled due to issues
         * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/68
         */
        $parser = new RobotsTxtParser($robotsTxtContent, 'UTF-8');

        $parser->setUserAgent('*');
        $this->assertTrue($parser->isAllowed("/"));
        $this->assertTrue($parser->isAllowed("/article"));
        $this->assertTrue($parser->isDisallowed("/temp"));
        $this->assertTrue($parser->isDisallowed("/Admin"));
        $this->assertTrue($parser->isDisallowed("/admin"));
        $this->assertTrue($parser->isDisallowed("/admin/cp/test/"));
        $this->assertFalse($parser->isDisallowed("/"));
        $this->assertFalse($parser->isDisallowed("/article"));
        $this->assertFalse($parser->isAllowed("/temp"));
        $this->assertFalse($parser->isDisallowed("/article"));

        $parser->setUserAgent('agentU/2.0.1');
        $this->assertTrue($parser->isAllowed("/foo"));
        $this->assertTrue($parser->isDisallowed("/bar"));

        $parser->setUserAgent('agentV');
        $this->assertTrue($parser->isDisallowed("/foo"));
        $this->assertTrue($parser->isAllowed("/bar"));
        $this->assertTrue($parser->isAllowed("/Foo"));

        $parser->setUserAgent('agentW');
        $this->assertTrue($parser->isDisallowed("/foo"));
        $this->assertTrue($parser->isAllowed("/bar"));
        $this->assertTrue($parser->isAllowed("/Foo"));

        $parser->setUserAgent('spiderX/1.0');
        $this->assertTrue($parser->isAllowed("/temp"));
        $this->assertTrue($parser->isDisallowed("/assets"));
        $this->assertTrue($parser->isAllowed("/forum"));
        $this->assertFalse($parser->isDisallowed("/temp"));
        $this->assertFalse($parser->isAllowed("/assets"));
        $this->assertFalse($parser->isDisallowed("/forum"));

        $parser->setUserAgent('botY-test');
        $this->assertTrue($parser->isDisallowed("/"));
        $this->assertTrue($parser->isDisallowed("/forum"));
        $this->assertTrue($parser->isAllowed("/forum/"));
        $this->assertTrue($parser->isDisallowed("/forum/topic"));
        $this->assertTrue($parser->isDisallowed("/public"));
        $this->assertFalse($parser->isAllowed("/"));
        $this->assertFalse($parser->isAllowed("/forum"));
        $this->assertFalse($parser->isDisallowed("/forum/"));
        $this->assertFalse($parser->isAllowed("/forum/topic"));
        $this->assertFalse($parser->isAllowed("/public"));

        $parser->setUserAgent('crawlerZ');
        $this->assertTrue($parser->isAllowed("/"));
        $this->assertTrue($parser->isDisallowed("/forum"));
        $this->assertTrue($parser->isDisallowed("/public"));
        $this->assertFalse($parser->isDisallowed("/"));
        $this->assertFalse($parser->isAllowed("/forum"));
        $this->assertFalse($parser->isAllowed("/public"));
    }

    /**
     * Generate test data
     *
     * @return array
     */
    public function generateDataForTest()
    {
        return [
            [
                <<<ROBOTS
User-agent: anyone
User-agent: *
Disallow: /admin
Disallow: /admin
Disallow: /Admin
Disallow: /temp#comment
Disallow: /forum
Disallow: /admin/cp/test/

User-agent: agentU/2.0
Disallow: /bar
Allow: /foo

User-agent: agentV
User-agent: agentW
Disallow: /foo
Allow: /bar #comment

User-agent: spiderX
Disallow:
Disallow: /admin#
Disallow: /assets

User-agent: botY
Disallow: /
Allow: &&/1@| #invalid
Allow: /forum/$
Allow: /article

User-agent: crawlerZ
Disallow:
Disallow: /
Allow: /$
ROBOTS
            ]
        ];
    }
}
