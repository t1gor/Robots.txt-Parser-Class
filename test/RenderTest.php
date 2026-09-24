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

        $parser = (new RobotsTxtParser())->setContent($robotsTxtContent);

        $this->assertEquals($rendered, $parser->render("\n"));
    }

    /**
     * The comparator returned a bool, which PHP 8.3+ deprecates for usort() - a notice on every
     * render(), even though the order it produced happened to be right.
     */
    /**
     * Delays are scalars in the tree, not lists, so they take render()'s other branch.
     * Deliberately no Host or Sitemap here - see testRenderDuplicatesHostAndSitemap().
     *
     * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::render
     */
    public function testRenderEmitsScalarDirectivesWithoutNotices()
    {
        $parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\nCrawl-Delay: 2.5\n");

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
        $this->assertStringContainsString('Crawl-delay: 2.5', $rendered);
        $this->assertStringContainsString('Disallow: /admin', $rendered);
    }

    /**
     * render() emits host and sitemap twice - once from the per-user-agent tree loop, then again
     * from its own trailing block - and the trailing "Host: " concatenates the array that a
     * no-argument getHost() returns, so it renders the literal "Array" and raises a notice.
     *
     * render() is deprecated in favour of getReader()->getContentRaw(), so this records the
     * behaviour rather than asserting the fix.
     *
     * @group known-issues
     */
    public function testRenderDuplicatesHostAndSitemap()
    {
        $parser = (new RobotsTxtParser())->setContent(
            "User-agent: *\nDisallow: /admin\nHost: example.com\nSitemap: https://example.com/sitemap.xml\n"
        );

        $rendered = $parser->render("\n");

        $this->assertSame(1, substr_count($rendered, 'Host: example.com'), $rendered);
        $this->assertStringNotContainsString('Host: Array', $rendered, $rendered);
        $this->assertSame(1, substr_count($rendered, 'Sitemap: https://example.com/sitemap.xml'), $rendered);
    }

    public function testRenderSortsShorterPathsLaterAndStaysQuiet()
    {
        $parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /temp\nDisallow: /admin/test/\nDisallow: /forum\n");

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
