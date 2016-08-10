<?php

class RenderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @param string $robotsTxtContent
     * @param string|false $rendered
     */
    public function testRender($robotsTxtContent, $rendered)
    {
        $parser = new RobotsTxtParser($robotsTxtContent);
        $this->assertInstanceOf('RobotsTxtParser', $parser);

        $this->assertEquals($rendered, $parser->render("\n"));
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
User-agent: *
Disallow: Host: www.example.com
Disallow: Clean-param: token /public/users
Disallow: Clean-param: uid /public/users
Disallow: /admin/te*
Disallow: /temp
Disallow: /forum
Disallow: /admin/test/
Allow: /public
Crawl-delay: 5
Cache-delay: 10
User-agent: bingbot
Disallow: /
User-agent: yahoo! slurp
Disallow: /
Host: example.com
Sitemap: http://example.com/sitemap.xml
Sitemap: http://example.com/sitemap.xml.gz
User-agent: duckduckgo
Disallow: /
ROBOTS
                ,
                <<<RENDERED
User-agent: yahoo! slurp
Disallow: /

User-agent: duckduckgo
Disallow: /

User-agent: bingbot
Disallow: /

User-agent: *
Disallow: Clean-param: token /public/users
Disallow: Clean-param: uid /public/users
Disallow: Host: www.example.com
Disallow: /admin/test/
Disallow: /admin/te*
Disallow: /forum
Disallow: /temp
Allow: /public
Crawl-delay: 5
Cache-delay: 10

Host: example.com
Sitemap: http://example.com/sitemap.xml
Sitemap: http://example.com/sitemap.xml.gz

RENDERED
            ]
        ];
    }
}
