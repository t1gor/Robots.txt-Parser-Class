<?php declare(strict_types=1);

namespace Writer;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\Writer\StreamWriter;
use t1gor\RobotsTxtParser\Writer\StringWriter;

/**
 * Parse a document, hand the rules to a writer, read the robots.txt back out. The parser knows
 * nothing about the writers - this is the seam between them.
 *
 * @covers \t1gor\RobotsTxtParser\Writer\AbstractWriter
 * @covers \t1gor\RobotsTxtParser\Writer\StringWriter
 */
class RenderIntegrationTest extends TestCase {

	private function render(RobotsTxtParser $parser, string $eol = "\n", ?string $encoding = null): string {
		return $this->into(
			(new StringWriter($parser->getLogger()))->setTree($parser->getRules())->setEol($eol)->setEncoding($encoding)
		);
	}

	/** Writers write, so asking for the bytes back means giving them somewhere to put them. */
	private function into(object $writer): string {
		$buffer = fopen('php://temp', 'r+');

		$writer->setOutput($buffer)->render();
		rewind($buffer);

		$document = (string) stream_get_contents($buffer);
		fclose($buffer);

		return $document;
	}

	/**
	 * Groups sorted with the catch-all last, agents sharing rules merged, rules longest first,
	 * and Host/Clean-param/Sitemap collected into one trailing block.
	 */
	public function testRendersANormalisedDocument() {
		$parser = (new RobotsTxtParser())->setContent(<<<ROBOTS
			User-agent: *
			Disallow: Host: www.example.com
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
			Clean-param: token&uid /public/users
			User-agent: duckduckgo
			Disallow: /
			ROBOTS);

		$this->assertSame(<<<RENDERED
			User-agent: bingbot
			User-agent: duckduckgo
			User-agent: yahoo! slurp
			Disallow: /

			User-agent: *
			Disallow: /admin/test/
			Disallow: /admin/te*
			Allow: /public
			Disallow: /forum
			Disallow: /temp
			Crawl-delay: 5
			Cache-delay: 10

			Host: example.com
			Clean-param: token&uid /public/users
			Sitemap: http://example.com/sitemap.xml
			Sitemap: http://example.com/sitemap.xml.gz

			RENDERED, $this->render($parser));
	}

	/** Normalised means settled: parsing the output and writing it again changes nothing. */
	public function testRenderingIsIdempotent() {
		$robots = "User-agent: *\nDisallow: /admin\nAllow: /admin/public\nCrawl-delay: 2.5\n"
			. "User-agent: bingbot\nDisallow: /\nHost: example.com\nSitemap: https://example.com/s.xml\n"
			. "Clean-param: token /public/users\n";

		$once  = $this->render((new RobotsTxtParser())->setContent($robots));
		$twice = $this->render((new RobotsTxtParser())->setContent($once));

		$this->assertSame($once, $twice);
	}

	/** Read as Windows-1251, written back as Windows-1251 - the tree in between is UTF-8. */
	public function testRendersBackInTheEncodingItWasRead() {
		$parser = (new RobotsTxtParser())->setContent(
			fopen(__DIR__ . '/../Fixtures/market-yandex-Windows-1251.txt', 'r'),
			'Windows-1251'
		);

		$rendered = $this->render($parser, "\n", 'Windows-1251');

		$this->assertSame($this->render($parser), iconv('Windows-1251', 'UTF-8', $rendered));
		$this->assertStringContainsString('User-agent: ', $rendered);
	}

	public function testDefaultsToTheLineEndingRobotsTxtIsServedWith() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\n");

		$this->assertSame(
			"User-agent: *\r\nDisallow: /admin\r\n",
			$this->into((new StringWriter())->setTree($parser->getRules()))
		);
	}

	/** Delays are scalars in the tree, so they take a different branch from the path lists. */
	public function testEmitsScalarDirectivesWithoutNotices() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\nCrawl-Delay: 2.5\n");

		$raised = [];
		set_error_handler(function (int $no, string $str) use (&$raised) {
			$raised[] = $str;

			return true;
		});

		try {
			$rendered = $this->render($parser);
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised, 'no PHP notices expected');
		$this->assertSame("User-agent: *\nDisallow: /admin\nCrawl-delay: 2.5\n", $rendered);
	}

	/** Both used to be written twice - once per group, then again in the trailing block. */
	public function testHostAndSitemapAreWrittenOnce() {
		$parser = (new RobotsTxtParser())->setContent(
			"User-agent: *\nDisallow: /admin\nHost: example.com\nSitemap: https://example.com/sitemap.xml\n"
		);

		$rendered = $this->render($parser);

		$this->assertSame(1, substr_count($rendered, 'Host: example.com'), $rendered);
		$this->assertStringNotContainsString('Host: Array', $rendered, $rendered);
		$this->assertSame(1, substr_count($rendered, 'Sitemap: https://example.com/sitemap.xml'), $rendered);
	}

	/** Clean-param sits at the top of the tree next to the agents, and is not one of them. */
	public function testCleanParamIsNotRenderedAsAUserAgent() {
		$parser = (new RobotsTxtParser())->setContent(
			"User-agent: *\nDisallow: /admin\nClean-param: token /public/users\n"
		);

		$rendered = $this->render($parser);

		$this->assertStringNotContainsString('User-agent: clean-param', $rendered);
		$this->assertStringContainsString('Clean-param: token /public/users', $rendered);
	}

	/** Hand the writer the parser's logger and everything it drops is reported alongside the parse. */
	public function testAWriterCanShareTheParsersLogger() {
		$log     = new Logger(static::class);
		$handler = new TestHandler(LogLevel::DEBUG);
		$log->pushHandler($handler);

		// the sitemap processor stores whatever it is given, so the writer is the one that says no
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\nSitemap: /sitemap.xml\n");
		$parser->setLogger($log);

		$this->assertSame("User-agent: *\nDisallow: /admin\n", $this->render($parser));

		$messages = array_map('extractMessageFromRecord', $handler->getRecords());

		$this->assertNotEmpty(
			preg_grep('#Sitemap "/sitemap.xml" dropped for \*#', $messages),
			stringifyLogs($handler->getRecords())
		);
	}

	/** Either writer, same tree, same bytes - only how they get there differs. */
	public function testBothWritersProduceTheSameDocument() {
		$parser = (new RobotsTxtParser())->setContent(
			"User-agent: *\nDisallow: /admin\nAllow: /admin/public\nUser-agent: bingbot\nDisallow: /\n"
		);

		$stream = fopen('php://memory', 'r+');
		(new StreamWriter())->setTree($parser->getRules())->setEol("\n")->setOutput($stream)->render();
		rewind($stream);
		$streamed = stream_get_contents($stream);
		fclose($stream);

		$this->assertSame($this->render($parser), $streamed);
	}
}
