<?php

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class RenderTest extends TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @param string $robotsTxtContent
     * @param string|false $rendered
     */
    public function testRender($robotsTxtContent, $rendered)
    {
	    $this->markTestSkipped('@TODO');

        $parser = new RobotsTxtParser($robotsTxtContent);

        $this->assertEquals($rendered, $parser->render("\n"));
    }

    /**
     * The comparator returned a bool, which PHP 8.3+ deprecates for usort() - a notice on every
     * render(), even though the order it produced happened to be right.
     */
    public function testRenderSortsShorterPathsLaterAndStaysQuiet()
    {
        $parser = new RobotsTxtParser("User-agent: *\nDisallow: /temp\nDisallow: /admin/test/\nDisallow: /forum\n");

        $raised = [];
        set_error_handler(function (int $no, string $str) use (&$raised) {
            $raised[] = $str;
            return true;
        });

        try {
            $rendered = $parser->render("\n");
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $raised, 'no PHP notices expected');
        $this->assertStringContainsString(
            "Disallow: /admin/test/\nDisallow: /forum\nDisallow: /temp",
            $rendered
        );
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
