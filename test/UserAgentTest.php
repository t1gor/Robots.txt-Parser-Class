<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::isAllowed
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::isDisallowed
 */
class UserAgentTest extends TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @param string $robotsTxtContent
     */
    public function testUserAgentPermission(string $robotsTxtContent)
    {
        // init parser
        $parser = new RobotsTxtParser($robotsTxtContent);

        $this->assertTrue($parser->isAllowed("/"));
        $this->assertTrue($parser->isAllowed("/article"));
        $this->assertTrue($parser->isDisallowed("/temp"));

        $this->assertFalse($parser->isDisallowed("/"));
        $this->assertFalse($parser->isDisallowed("/article"));
        $this->assertFalse($parser->isAllowed("/temp"));

        $this->assertTrue($parser->isAllowed("/foo", "agentU/2.0.1"));
        $this->assertTrue($parser->isDisallowed("/bar", "agentU/2.0.1"));

        $this->assertTrue($parser->isDisallowed("/foo", "agentV"));
        $this->assertTrue($parser->isAllowed("/bar", "agentV"));
        $this->assertTrue($parser->isDisallowed("/foo", "agentW"));
        $this->assertTrue($parser->isAllowed("/bar", "agentW"));

        $this->assertTrue($parser->isAllowed("/temp", "spiderX/1.0"));
        $this->assertTrue($parser->isDisallowed("/assets", "spiderX/1.0"));
        $this->assertTrue($parser->isAllowed("/forum", "spiderX/1.0"));

        $this->assertFalse($parser->isDisallowed("/temp", "spiderX/1.0"));
        $this->assertFalse($parser->isAllowed("/assets", "spiderX/1.0"));
        $this->assertFalse($parser->isDisallowed("/forum", "spiderX/1.0"));

        $this->assertTrue($parser->isDisallowed("/", "botY-test"));
        $this->assertTrue($parser->isAllowed("/forum/", "botY-test"));
        $this->assertTrue($parser->isDisallowed("/forum/topic", "botY-test"));
        $this->assertTrue($parser->isDisallowed("/public", "botY-test"));

        $this->assertFalse($parser->isAllowed("/", "botY-test"));
        $this->assertFalse($parser->isDisallowed("/forum/", "botY-test"));
        $this->assertFalse($parser->isAllowed("/forum/topic", "botY-test"));
        $this->assertFalse($parser->isAllowed("/public", "botY-test"));

        $this->assertTrue($parser->isAllowed("/", "crawlerZ"));
        $this->assertTrue($parser->isDisallowed("/forum", "crawlerZ"));
        $this->assertTrue($parser->isDisallowed("/public", "crawlerZ"));

        $this->assertFalse($parser->isDisallowed("/", "crawlerZ"));
        $this->assertFalse($parser->isAllowed("/forum", "crawlerZ"));
        $this->assertFalse($parser->isAllowed("/public", "crawlerZ"));
    }

    /**
     * Generate test case data
     * @return array
     */
    public function generateDataForTest()
    {
        return array(
            array(
                "
				User-agent: *
				Disallow: /admin
				Disallow: /temp
				Disallow: /forum
				
				User-agent: agentU/2.0
				Disallow: /bar
				Allow: /foo
				
				User-agent: agentV
				User-agent: agentW
				Disallow: /foo
				Allow: /bar
				
				User-agent: spiderX
				Disallow:
				Disallow: /admin
				Disallow: /assets
				
				User-agent: botY
				Disallow: /
				Allow: /forum/$
				Allow: /article
				
				User-agent: crawlerZ
				Disallow:
				Disallow: /
				Allow: /$
			"
            )
        );
    }
}
