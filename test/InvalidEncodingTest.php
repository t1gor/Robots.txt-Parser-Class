<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * Typos and uninstalled charsets are common when the encoding comes from an HTTP header.
 * They must be ignored quietly, not surfaced as PHP warnings.
 *
 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/70
 *
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::setEncoding
 */
class InvalidEncodingTest extends TestCase {

	public function invalidEncodingProvider(): array {
		return [
			'invalid'          => ['UTF9'],
			'typo'             => ['ASCI'],
			'missing dash'     => ['ISO8859'],
			'nonsense'         => ['not-an-encoding-at-all'],
		];
	}

	/**
	 * @dataProvider invalidEncodingProvider
	 */
	public function testInvalidEncodingRaisesNoWarning(string $encoding) {
		$raised = [];
		set_error_handler(function (int $no, string $str) use (&$raised) {
			$raised[] = $str;
			return true;
		});

		try {
			$parser = new RobotsTxtParser("User-agent: *\nDisallow: /admin\n", $encoding);
			$rules  = $parser->getRules();
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $raised, 'no PHP warning expected');
		$this->assertSame(['/admin'], $rules['*']['disallow'], 'content is still parsed as-is');
	}

	/**
	 * @dataProvider invalidEncodingProvider
	 */
	public function testInvalidEncodingIsLogged(string $encoding) {
		$log = new Logger(static::class);
		$log->pushHandler($handler = new TestHandler(LogLevel::DEBUG));

		$parser = new RobotsTxtParser("User-agent: *\nDisallow: /admin\n", $encoding);
		$parser->setLogger($log);
		$parser->getRules();

		$this->assertTrue(
			$handler->hasRecordThatContains('Unsupported encoding', LogLevel::WARNING),
			stringifyLogs($handler->getRecords())
		);

		// the PHP error we swallowed is kept in context, so the cause stays traceable
		$suppressed = array_values(array_filter($handler->getRecords(), function (array $record): bool {
			return strpos($record['message'], 'Suppressed while applying') === 0;
		}));

		$this->assertCount(1, $suppressed, stringifyLogs($handler->getRecords()));
		// PHP 7.4 says "unable", PHP 8 says "Unable"
		$this->assertStringContainsStringIgnoringCase(
			'nable to create or locate filter',
			$suppressed[0]['context']['error']
		);
	}

	/** Matching still works when the encoding was rejected. */
	public function testRulesStillApplyAfterUnsupportedEncoding() {
		$parser = new RobotsTxtParser("User-agent: *\nDisallow: /admin\n", 'UTF9');

		$this->assertTrue($parser->isDisallowed('/admin'));
		$this->assertTrue($parser->isAllowed('/public'));
	}

	/** An empty encoding means "nothing to convert", not a broken filter name. */
	public function testEmptyEncodingIsANoOp() {
		$log = new Logger(static::class);
		$log->pushHandler($handler = new TestHandler(LogLevel::DEBUG));

		$parser = new RobotsTxtParser("User-agent: *\nDisallow: /admin\n", '');
		$parser->setLogger($log);

		$this->assertSame(['/admin'], $parser->getRules()['*']['disallow']);
		$this->assertFalse($handler->hasRecordThatContains('Adding encoding filter', LogLevel::DEBUG));
	}

	/**
	 * A charset iconv knows, but that the file isn't actually in, converts to nothing -
	 * so every rule vanishes and everything is allowed. Fails open, silently.
	 *
	 * @group known-issues
	 */
	public function testSupportedButWrongEncodingDoesNotDiscardEveryRule() {
		$parser = new RobotsTxtParser("User-agent: *\nDisallow: /admin\n", 'OSF10020402');

		$this->assertTrue($parser->isDisallowed('/admin'));
	}

	/** A supported encoding is unaffected. */
	public function testSupportedEncodingStillApplies() {
		$log = new Logger(static::class);
		$log->pushHandler($handler = new TestHandler(LogLevel::DEBUG));

		$parser = new RobotsTxtParser(
			fopen(__DIR__ . '/Fixtures/market-yandex-Windows-1251.txt', 'r'),
			'Windows-1251'
		);
		$parser->setLogger($log);
		$parser->getRules();

		$this->assertTrue(
			$handler->hasRecord('Adding encoding filter convert.iconv.Windows-1251/utf-8', LogLevel::DEBUG),
			stringifyLogs($handler->getRecords())
		);
		$this->assertFalse($handler->hasRecordThatContains('Unsupported encoding', LogLevel::WARNING));
	}
}
