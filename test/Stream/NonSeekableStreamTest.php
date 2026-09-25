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

	protected function setUp(): void {
		if (!in_array(OnceOnlyStream::PROTOCOL, stream_get_wrappers(), true)) {
			stream_wrapper_register(OnceOnlyStream::PROTOCOL, OnceOnlyStream::class);
		}
	}

	/**
	 * A stream in pure PHP rather than a subprocess: `cat` and /dev/null are not there on Windows,
	 * and this reads the same everywhere.
	 *
	 * @return resource
	 */
	private function unseekable(string $body = self::CONTENT) {
		OnceOnlyStream::$body = $body;

		return fopen(OnceOnlyStream::PROTOCOL . '://document', 'r');
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

	/** The fixture has to be what it claims, or none of the rest means anything. */
	public function testTheFixtureCannotBeRewound() {
		$stream = $this->unseekable();

		$this->assertSame('User-', fread($stream, 5));
		$this->assertFalse(@rewind($stream), 'the fixture has to refuse to seek');
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
		$parser->setContent($this->unseekable());

		$this->assertSame(['/admin'], $parser->getRules()['*']['disallow'] ?? []);
	}

	/**
	 * @dataProvider unlimitedConfigurations
	 */
	public function testNothingIsRaisedAlongTheWay(Configuration $config) {
		$raised = $this->phpWarningsDuring(function () use ($config) {
			$parser = new RobotsTxtParser($config);
			$parser->setContent($this->unseekable());
			$parser->getRules();
		});

		$this->assertSame([], $raised, 'a stream that cannot seek is not an error');
	}

	/** A byte limit copies the document into a stream of our own, which can seek. */
	public function testTheDefaultByteLimitWasNeverAffected() {
		$parser = new RobotsTxtParser(new Configuration(parseEncoding: 'Windows-1251'));
		$parser->setContent($this->unseekable());

		$this->assertSame(['/admin'], $parser->getRules()['*']['disallow']);
	}

	/** Cyrillic still decodes: skipping the sample must not skip the filter. */
	public function testTheEncodingIsStillApplied() {
		$cp1251 = (string) file_get_contents(__DIR__ . '/../Fixtures/cp1251-real-bytes.txt');
		$parser = new RobotsTxtParser(new Configuration(byteLimit: null, parseEncoding: 'Windows-1251'));
		$parser->setContent($this->unseekable($cp1251));

		$this->assertContains('/каталог', $parser->getRules()['*']['disallow']);
	}

	/** An unusable charset is still caught - by its name, which needs no bytes. */
	public function testAnUnknownCharsetIsStillReported() {
		$reader = GeneratorBasedReader::fromStream($this->unseekable(), new Configuration(byteLimit: null));
		$reader->setEncoding('UTF9');

		$this->assertSame([], array_values(array_filter(
			$reader->filters(),
			static fn (string $name): bool => str_starts_with($name, 'convert.iconv')
		)), 'a filter that cannot work must never be attached');
	}

	public function testGetContentRawStillReadsOnce() {
		$reader = GeneratorBasedReader::fromStream($this->unseekable(), new Configuration(byteLimit: null));

		$this->assertStringContainsString('/admin', $reader->getContentRaw());
	}

	/**
	 * The wrapper above is still advertised as seekable; a pipe is the real thing, where the
	 * metadata says so too. No `cat` on Windows, so this one only runs where there is a shell.
	 *
	 * @requires OS Linux|Darwin
	 */
	public function testAPipeWhoseMetadataSaysUnseekableWorksTheSameWay() {
		$path = (string) tempnam(sys_get_temp_dir(), 'rtp');
		file_put_contents($path, self::CONTENT);

		try {
			$pipe = popen('cat -- ' . escapeshellarg($path) . ' 2>/dev/null', 'r');

			$this->assertFalse(stream_get_meta_data($pipe)['seekable'], 'a pipe really cannot seek');

			$parser = new RobotsTxtParser(new Configuration(byteLimit: null, parseEncoding: 'Windows-1251'));
			$parser->setContent($pipe);

			$this->assertSame(['/admin'], $parser->getRules()['*']['disallow'] ?? []);
		} finally {
			@unlink($path);
		}
	}
}

/** Reads its body once and refuses to seek - no stream_seek(), so rewind() fails. */
class OnceOnlyStream {

	const PROTOCOL = 'rtp.once';

	/** @var resource set by stream_wrapper_register, unused but required */
	public $context;

	public static string $body = '';

	private int $at = 0;

	public function stream_open(string $path, string $mode, int $options, ?string &$opened): bool {
		$this->at = 0;

		return true;
	}

	public function stream_read(int $count): string {
		$chunk = substr(static::$body, $this->at, $count);
		$this->at += strlen($chunk);

		return $chunk;
	}

	public function stream_eof(): bool {
		return $this->at >= strlen(static::$body);
	}

	public function stream_stat(): array {
		return [];
	}
}
