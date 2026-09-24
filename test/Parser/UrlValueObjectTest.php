<?php declare(strict_types=1);

namespace Parser;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Parser\Url;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * Url is a value object now; the log line it used to emit comes from the parser.
 *
 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/139
 *
 * @covers \t1gor\RobotsTxtParser\Parser\Url::isReducedToPath
 */
class UrlValueObjectTest extends TestCase {

	/** Deliberate BC break. */
	public function testUrlIsNoLongerLoggerAware() {
		$this->assertNotInstanceOf(LoggerAwareInterface::class, new Url('http://example.com/'));
		$this->assertFalse(method_exists(Url::class, 'setLogger'));
	}

	/** @dataProvider reductionProvider */
	public function testIsReducedToPath(string $url, bool $expected) {
		$this->assertSame($expected, (new Url($url))->isReducedToPath());
	}

	public function reductionProvider(): array {
		return [
			'absolute http'      => ['http://example.com/catalog/', true],
			'absolute with port' => ['http://example.com:8080/catalog/', true],
			'bare path'          => ['/catalog/', false],
			'unsupported scheme' => ['mailto:someone@example.com', false],
			'invalid host'       => ['http://exa_mple.com/p', false],
			'unparseable port'   => ['http://example.com:abc/p', false],
			'empty'              => ['', false],
		];
	}

	public function testParserLogsUrlsItCouldNotReduceToAPath() {
		$handler = new TestHandler(LogLevel::DEBUG);
		$logger  = new Logger(static::class);
		$logger->pushHandler($handler);

		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /secret\n");
		$parser->setLogger($logger);

		$parser->isDisallowed('mailto:someone@example.com');

		$this->assertTrue($handler->hasRecordThatContains('Could not extract a path from mailto:someone@example.com', Level::Debug));
	}

	public function testParserStaysQuietForUrlsItCanReduce() {
		$handler = new TestHandler(LogLevel::DEBUG);
		$logger  = new Logger(static::class);
		$logger->pushHandler($handler);

		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /secret\n");
		$parser->setLogger($logger);

		$parser->isDisallowed('http://example.com/secret');

		$this->assertFalse($handler->hasRecordThatContains('Could not extract a path', Level::Debug));
	}
}
