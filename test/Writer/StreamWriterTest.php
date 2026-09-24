<?php declare(strict_types=1);

namespace Writer;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Exception\EncodingFailedException;
use t1gor\RobotsTxtParser\Exception\WriteFailedException;
use t1gor\RobotsTxtParser\Writer\StreamWriter;
use t1gor\RobotsTxtParser\Writer\StringWriter;

/**
 * @covers \t1gor\RobotsTxtParser\Writer\StreamWriter
 */
class StreamWriterTest extends TestCase {

	private const TREE = ['*' => ['disallow' => ['/admin'], 'allow' => ['/admin/public']]];

	private ?StreamWriter $writer;
	private ?TestHandler $handler;

	public function setUp(): void {
		$this->handler = new TestHandler(LogLevel::DEBUG);
		$log           = new Logger(static::class);
		$log->pushHandler($this->handler);

		$this->writer = new StreamWriter($log);
	}

	public function tearDown(): void {
		$this->writer = $this->handler = null;
	}

	private function logged(string $message): bool {
		foreach ($this->handler->getRecords() as $record) {
			if (str_contains($record['message'], $message)) {
				return true;
			}
		}

		return false;
	}

	/** @return array{0: string, 1: int} what landed in the stream, and what render() reported */
	private function writeOut(array $tree, string $eol = "\n", ?string $encoding = null): array {
		$stream  = fopen('php://memory', 'r+');
		$written = $this->writer->setTree($tree)->setEol($eol)->setEncoding($encoding)->setOutput($stream)->render();

		rewind($stream);
		$out = stream_get_contents($stream);
		fclose($stream);

		return [$out, $written];
	}

	/** What the simple writer makes of the same tree. */
	private function naively(array $tree, string $eol = "\n"): string {
		$stream = fopen('php://memory', 'r+');
		(new StringWriter())->setTree($tree)->setEol($eol)->setOutput($stream)->render();
		rewind($stream);
		$out = (string) stream_get_contents($stream);
		fclose($stream);

		return $out;
	}

	/** Same normalising, same document - only when it reaches the stream differs. */
	public function testWritesTheSameDocumentTheNaiveWriterDoes() {
		[$out] = $this->writeOut(self::TREE);

		$this->assertSame($this->naively(self::TREE), $out);
	}

	public function testReportsWhatItWrote() {
		[$out, $written] = $this->writeOut(self::TREE);

		$this->assertSame(strlen($out), $written);
	}

	public function testNothingIsWrittenForAnEmptyTree() {
		[$out, $written] = $this->writeOut([]);

		$this->assertSame('', $out);
		$this->assertSame(0, $written);
		$this->assertTrue($this->logged('Nothing valid left to render'), 'the reason should be logged');
	}

	public function testRejectsAnOutputThatIsNotAStream() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Argument must be a valid resource type. string given.');

		$this->writer->setOutput('robots.txt');
	}

	public function testConvertsEachLineOnItsWayOut() {
		[$out, $written] = $this->writeOut(['*' => ['disallow' => ['/админка']]], "\n", 'Windows-1251');
		$expected        = iconv('UTF-8', 'Windows-1251', "User-agent: *\nDisallow: /админка\n");

		$this->assertSame($expected, $out);
		$this->assertTrue($this->logged('Encoding you are passing is different from UTF-8'));

		// the count is what the stream took, not what was handed to a filter before it shrank
		$this->assertSame(strlen($expected), $written);
		$this->assertLessThan(strlen("User-agent: *\nDisallow: /админка\n"), $written);
	}

	public function testUtf8NeedsNoConversionAndNoWarning() {
		[$out] = $this->writeOut(['*' => ['disallow' => ['/админка']]], "\n", 'utf8');

		$this->assertSame("User-agent: *\nDisallow: /админка\n", $out);
		$this->assertFalse($this->logged('Encoding you are passing is different from UTF-8'));
	}

	public function testRefusesAnEncodingIconvDoesNotKnow() {
		$this->expectException(EncodingFailedException::class);
		$this->expectExceptionMessage('Cannot write this robots.txt as NoSuchCharset');

		$this->writeOut(self::TREE, "\n", 'NoSuchCharset');
	}

	/**
	 * The filter this used to lean on dropped the whole chunk it could not map, so the rule simply
	 * vanished from the file and nothing said so - a Disallow silently becoming crawlable.
	 */
	public function testRefusesToDropARuleTheEncodingCannotRepresent() {
		$this->expectException(EncodingFailedException::class);

		$this->writeOut(['*' => ['disallow' => ['/a→b', '/ok']]], "\n", 'Windows-1251');
	}

	/** A write the stream will not take leaves an incomplete file, so it cannot pass unnoticed. */
	public function testThrowsWhenTheOutputWillNotTakeTheBytes() {
		$readOnly = fopen('php://memory', 'r');

		$this->expectException(WriteFailedException::class);

		try {
			$this->writer->setTree(self::TREE)->setEol("\n")->setOutput($readOnly)->render();
		} finally {
			fclose($readOnly);
		}
	}

	/** The stream is the caller's: nothing of ours may outlive the call. */
	public function testTheCallersStreamIsLeftAlone() {
		$stream = fopen('php://memory', 'r+');
		$this->writer->setTree(self::TREE)->setEol("\n")->setEncoding('Windows-1251')->setOutput($stream)->render();

		fwrite($stream, 'админка');
		rewind($stream);
		$out = stream_get_contents($stream);
		fclose($stream);

		$this->assertStringEndsWith('админка', $out, 'the tail should be unconverted UTF-8');
	}

	/** A line at a time: the stream sees each one as its own write, not the document in one go. */
	public function testWritesALineAtATime() {
		LineRecorder::$chunks = [];
		stream_filter_register('rtp.recorder', LineRecorder::class);

		$stream = fopen('php://memory', 'r+');
		stream_filter_append($stream, 'rtp.recorder', STREAM_FILTER_WRITE);

		$this->writer->setTree(['*' => ['disallow' => ['/a', '/bb', '/ccc']]])->setEol("\n")->setOutput($stream)->render();
		fclose($stream);

		$this->assertSame([
			"User-agent: *\n",
			"Disallow: /ccc\n",
			"Disallow: /bb\n",
			"Disallow: /a\n",
		], LineRecorder::$chunks);
	}
}

/** Records every write the stream is handed, so a test can see how the document arrived. */
class LineRecorder extends \php_user_filter {

	public static array $chunks = [];

	public function filter($in, $out, &$consumed, $closing): int {
		while ($bucket = stream_bucket_make_writeable($in)) {
			static::$chunks[] = $bucket->data;
			$consumed        += $bucket->datalen;

			stream_bucket_append($out, $bucket);
		}

		return PSFS_PASS_ON;
	}
}
