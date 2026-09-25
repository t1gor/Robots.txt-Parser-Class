<?php declare(strict_types=1);

namespace Stream;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\Stream\GeneratorBasedReader;

/**
 * An http response or a pipe cannot be rewound, and with no byte limit there is no copy of it
 * either - so anything that reads it twice reads nothing the second time. Setting a charset used
 * to do exactly that while sampling the content, losing every rule.
 *
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::conversionErrors
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::rewindIfPossible
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::getContentIterated
 * @covers \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::getContentRaw
 */
class NonSeekableStreamTest extends TestCase {

	private const CONTENT = "User-agent: *\nDisallow: /admin\n";

	/** @var string[] written by {@see pipe()}, removed in tearDown */
	private array $temporary = [];

	protected function tearDown(): void {
		foreach ($this->temporary as $path) {
			@unlink($path);
		}

		$this->temporary = [];
	}

	/** @return resource a stream that really cannot seek, the way an http one cannot */
	private function pipe(string $body = self::CONTENT) {
		// through a file rather than the shell: the body is bytes, not necessarily text
		$path = (string) tempnam(sys_get_temp_dir(), 'rtp');
		file_put_contents($path, $body);
		$this->temporary[] = $path;

		// stderr dropped: a test that stops reading early leaves cat with a broken pipe to complain about
		$stream = popen('cat -- ' . escapeshellarg($path) . ' 2>/dev/null', 'r');

		$this->assertFalse(stream_get_meta_data($stream)['seekable'], 'the fixture has to be unseekable');

		return $stream;
	}

	/** @return string[] messages PHP raised while $run executed */
	private function phpWarningsDuring(callable $run): array {
		$raised = [];
		set_error_handler(function (int $number, string $message) use (&$raised): bool {
			$raised[] = $message;

			return true;
		});

		try {
			$run();
		} finally {
			restore_error_handler();
		}

		return $raised;
	}

	public function unlimitedConfigurations(): array {
		return [
			'parse encoding'   => [new Configuration(byteLimit: null, parseEncoding: 'Windows-1251')],
			'default encoding' => [new Configuration(byteLimit: null, defaultEncoding: 'Windows-1251')],
			'no encoding'      => [new Configuration(byteLimit: null)],
			'unknown charset'  => [new Configuration(byteLimit: null, parseEncoding: 'UTF9')],
		];
	}

	/**
	 * @dataProvider unlimitedConfigurations
	 */
	public function testRulesSurviveWithoutAByteLimit(Configuration $config) {
		$parser = new RobotsTxtParser($config);
		$parser->setContent($this->pipe());

		$this->assertSame(['/admin'], $parser->getRules()['*']['disallow'] ?? []);
	}

	/**
	 * @dataProvider unlimitedConfigurations
	 */
	public function testNothingIsRaisedAlongTheWay(Configuration $config) {
		$raised = $this->phpWarningsDuring(function () use ($config) {
			$parser = new RobotsTxtParser($config);
			$parser->setContent($this->pipe());
			$parser->getRules();
		});

		$this->assertSame([], $raised, 'a stream that cannot seek is not an error');
	}

	/** A byte limit copies the document into a stream of our own, which can seek. */
	public function testTheDefaultByteLimitWasNeverAffected() {
		$parser = new RobotsTxtParser(new Configuration(parseEncoding: 'Windows-1251'));
		$parser->setContent($this->pipe());

		$this->assertSame(['/admin'], $parser->getRules()['*']['disallow']);
	}

	/** Cyrillic still decodes: skipping the sample must not skip the filter. */
	public function testTheEncodingIsStillApplied() {
		$cp1251 = (string) file_get_contents(__DIR__ . '/../Fixtures/cp1251-real-bytes.txt');
		$parser = new RobotsTxtParser(new Configuration(byteLimit: null, parseEncoding: 'Windows-1251'));
		$parser->setContent($this->pipe($cp1251));

		$this->assertContains('/каталог', $parser->getRules()['*']['disallow']);
	}

	/** An unusable charset is still caught - by its name, which needs no bytes. */
	public function testAnUnknownCharsetIsStillReported() {
		$reader = GeneratorBasedReader::fromStream($this->pipe(), new Configuration(byteLimit: null));
		$reader->setEncoding('UTF9');

		$this->assertSame([], array_values(array_filter(
			$reader->filters(),
			static fn (string $name): bool => str_starts_with($name, 'convert.iconv')
		)), 'a filter that cannot work must never be attached');
	}

	public function testGetContentRawStillReadsOnce() {
		$reader = GeneratorBasedReader::fromStream($this->pipe(), new Configuration(byteLimit: null));

		$this->assertStringContainsString('/admin', $reader->getContentRaw());
	}
}
