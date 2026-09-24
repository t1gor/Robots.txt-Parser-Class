<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\Writer\AbstractWriter;
use t1gor\RobotsTxtParser\Writer\StreamWriter;
use t1gor\RobotsTxtParser\Writer\StringWriter;
use t1gor\RobotsTxtParser\Writer\WriterInterface;

/**
 * render() end to end: parse a document, write it back normalised.
 *
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::render
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::renderTo
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::writer
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::__toString
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::onLoggerSet
 */
class RenderTest extends TestCase {

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

			RENDERED, $parser->render("\n"));
	}

	/** Normalised means settled: parsing the output and writing it again changes nothing. */
	public function testRenderingIsIdempotent() {
		$robots = "User-agent: *\nDisallow: /admin\nAllow: /admin/public\nCrawl-delay: 2.5\n"
			. "User-agent: bingbot\nDisallow: /\nHost: example.com\nSitemap: https://example.com/s.xml\n"
			. "Clean-param: token /public/users\n";

		$once  = (new RobotsTxtParser())->setContent($robots)->render("\n");
		$twice = (new RobotsTxtParser())->setContent($once)->render("\n");

		$this->assertSame($once, $twice);
	}

	/** Read as Windows-1251, written back as Windows-1251 - the tree in between is UTF-8. */
	public function testRendersBackInTheEncodingItWasRead() {
		$parser = (new RobotsTxtParser())->setContent(
			fopen(__DIR__ . '/Fixtures/market-yandex-Windows-1251.txt', 'r'),
			'Windows-1251'
		);

		$rendered = $parser->render("\n", 'Windows-1251');

		$this->assertSame($parser->render("\n"), iconv('Windows-1251', 'UTF-8', $rendered));
		$this->assertStringContainsString('User-agent: ', $rendered);
	}

	public function testDefaultsToTheLineEndingRobotsTxtIsServedWith() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\n");

		$this->assertSame("User-agent: *\r\nDisallow: /admin\r\n", $parser->render());
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
			$rendered = $parser->render("\n");
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

		$rendered = $parser->render("\n");

		$this->assertSame(1, substr_count($rendered, 'Host: example.com'), $rendered);
		$this->assertStringNotContainsString('Host: Array', $rendered, $rendered);
		$this->assertSame(1, substr_count($rendered, 'Sitemap: https://example.com/sitemap.xml'), $rendered);
	}

	/** Clean-param sits at the top of the tree next to the agents, and is not one of them. */
	public function testCleanParamIsNotRenderedAsAUserAgent() {
		$parser = (new RobotsTxtParser())->setContent(
			"User-agent: *\nDisallow: /admin\nClean-param: token /public/users\n"
		);

		$rendered = $parser->render("\n");

		$this->assertStringNotContainsString('User-agent: clean-param', $rendered);
		$this->assertStringContainsString('Clean-param: token /public/users', $rendered);
	}

	/** Whatever the writer drops is reported through the logger the parser was given. */
	public function testWhatTheWriterDropsIsLoggedThroughTheParser() {
		$log     = new Logger(static::class);
		$handler = new TestHandler(LogLevel::DEBUG);
		$log->pushHandler($handler);

		// the sitemap processor stores whatever it is given, so the writer is the one that says no
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\nSitemap: /sitemap.xml\n");
		$parser->setLogger($log);

		$this->assertSame("User-agent: *\nDisallow: /admin\n", $parser->render("\n"));

		$messages = array_map('extractMessageFromRecord', $handler->getRecords());

		$this->assertNotEmpty(
			preg_grep('#Sitemap "/sitemap.xml" dropped for \*#', $messages),
			stringifyLogs($handler->getRecords())
		);
	}

	public function testUsesAnInjectedWriter() {
		$writer = new class implements WriterInterface {
			public array $seen = [];

			public function setLogger(\Psr\Log\LoggerInterface $logger): void {
				$this->seen['logger'] = get_class($logger);
			}

			public function setTree(array $tree): static {
				$this->seen['tree'] = $tree;

				return $this;
			}

			public function setEol(string $eol): static {
				return $this;
			}

			public function setEncoding(?string $encoding): static {
				$this->seen['encoding'] = $encoding;

				return $this;
			}

			public function setOutput($output): static {
				$this->seen['output'] = $output;

				return $this;
			}

			public function render(): int {
				return (int) fwrite($this->seen['output'], 'injected');
			}
		};

		$parser = (new RobotsTxtParser(writer: $writer))->setContent("User-agent: *\nDisallow: /admin\n");
		$parser->setLogger(new Logger(static::class));

		$this->assertSame('injected', $parser->render(eol: "\n", encoding: 'Windows-1251'));
		$this->assertSame('Windows-1251', $writer->seen['encoding']);
		$this->assertSame(['*' => ['disallow' => ['/admin']]], $writer->seen['tree']);
		$this->assertSame(Logger::class, $writer->seen['logger']);
	}

	/** The naive way in - render() with its defaults. */
	public function testCastingToStringRendersWithTheDefaults() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\n");

		$this->assertSame($parser->render(), (string) $parser);
		$this->assertSame("User-agent: *\r\nDisallow: /admin\r\n", (string) $parser);
	}

	public function testRenderToWritesTheSameDocumentRenderReturns() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\nAllow: /admin/public\n");
		$stream = fopen('php://memory', 'r+');

		$written = $parser->renderTo($stream, "\n");
		rewind($stream);

		$this->assertSame($parser->render("\n"), stream_get_contents($stream));
		$this->assertSame(strlen($parser->render("\n")), $written);

		fclose($stream);
	}

	/** One contract, so whichever writer was injected answers renderTo() too. */
	public function testAnInjectedStringWriterStillWritesToTheStream() {
		$writer = new StringWriter();
		$parser = (new RobotsTxtParser(writer: $writer))->setContent("User-agent: *\nDisallow: /admin\n");
		$stream = fopen('php://memory', 'r+');

		$parser->renderTo($stream, "\n");
		rewind($stream);

		$this->assertSame("User-agent: *\nDisallow: /admin\n", stream_get_contents($stream));
		$this->assertSame($writer, (new \ReflectionProperty(RobotsTxtParser::class, 'writer'))->getValue($parser));

		fclose($stream);
	}

	public function testAnInjectedStreamWriterIsTheOneUsed() {
		$writer = new class extends StreamWriter {
			public int $calls = 0;

			public function render(): int {
				$this->calls++;

				return parent::render();
			}
		};

		$parser = (new RobotsTxtParser(writer: $writer))->setContent("User-agent: *\nDisallow: /admin\n");
		$stream = fopen('php://memory', 'r+');

		$parser->renderTo($stream, "\n");
		fclose($stream);

		$this->assertSame(1, $writer->calls);
	}

	/** The parser hands its own logger over, so nothing the default writer reports is lost. */
	public function testDefaultWriterLogsThroughTheParsersLogger() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\n");

		$this->assertSame($parser->getLogger(), (new \ReflectionProperty(AbstractWriter::class, 'logger'))
			->getValue($this->defaultWriterOf($parser)));
	}

	/** The default has to answer the whole contract, so it is the streaming one. */
	public function testTheDefaultWriterStreams() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\n");

		$this->assertInstanceOf(StreamWriter::class, $this->defaultWriterOf($parser));
	}

	private function defaultWriterOf(RobotsTxtParser $parser): AbstractWriter {
		$parser->render();

		return (new \ReflectionProperty(RobotsTxtParser::class, 'writer'))->getValue($parser);
	}
}
