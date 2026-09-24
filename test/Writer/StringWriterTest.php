<?php declare(strict_types=1);

namespace Writer;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Exception\EncodingFailedException;
use t1gor\RobotsTxtParser\Exception\NoOutputException;
use t1gor\RobotsTxtParser\Exception\WriteFailedException;
use t1gor\RobotsTxtParser\Writer\StringWriter;
use t1gor\RobotsTxtParser\Writer\WriterInterface;

/**
 * The normalising both writers share, driven through the simple one.
 *
 * @covers \t1gor\RobotsTxtParser\Writer\AbstractWriter
 * @covers \t1gor\RobotsTxtParser\Writer\StringWriter
 */
class StringWriterTest extends TestCase {

	private ?StringWriter $writer;
	private ?TestHandler $handler;

	public function setUp(): void {
		$this->handler = new TestHandler(LogLevel::DEBUG);
		$log           = new Logger(static::class);
		$log->pushHandler($this->handler);

		$this->writer = new StringWriter($log);
	}

	public function tearDown(): void {
		$this->writer = $this->handler = null;
	}

	/** Level-agnostic on purpose: the test cares that it was reported, not how loudly. */
	private function logged(string $message): bool {
		foreach ($this->handler->getRecords() as $record) {
			if (str_contains($record['message'], $message)) {
				return true;
			}
		}

		return false;
	}

	private function assertLogged(string $message): void {
		$this->assertTrue($this->logged($message), stringifyLogs($this->handler->getRecords()));
	}

	/** @return string what the writer put into a stream of its own */
	private function render(array $tree, ?string $encoding = null, string $eol = "\n"): string {
		$out = fopen('php://memory', 'r+');

		$written = $this->writer->setTree($tree)->setEol($eol)->setEncoding($encoding)->setOutput($out)->render();

		rewind($out);
		$document = (string) stream_get_contents($out);
		fclose($out);

		$this->assertSame(strlen($document), $written, 'render() should report what it wrote');

		return $document;
	}

	public function testRendersAGroupWithCanonicalDirectiveCasing() {
		$rendered = $this->render(['*' => ['disallow' => ['/admin'], 'allow' => ['/admin/public']]]);

		$this->assertSame("User-agent: *\nAllow: /admin/public\nDisallow: /admin\n", $rendered);
	}

	public function testDefaultEolIsCrLf() {
		$this->assertSame(
			"User-agent: *\r\nDisallow: /\r\n",
			$this->render(['*' => ['disallow' => ['/']]], null, WriterInterface::DEFAULT_EOL)
		);
	}

	public function testInterfaceDefaultEolIsTheWriterDefault() {
		$this->assertSame("\r\n", WriterInterface::DEFAULT_EOL);
	}

	/** Longest first, so a reader that stops at the first match still gets the specific rule. */
	public function testSortsRulesByDescendingLength() {
		$rendered = $this->render(['*' => [
			'allow'    => ['/themes', '/themes/flowers/roses'],
			'disallow' => ['/themes/flowers'],
		]]);

		$this->assertSame(
			"User-agent: *\nAllow: /themes/flowers/roses\nDisallow: /themes/flowers\nAllow: /themes\n",
			$rendered
		);
	}

	/** Ties go to allow, which is how the spec resolves them. */
	public function testAllowWinsALengthTie() {
		$rendered = $this->render(['*' => ['disallow' => ['/abcd'], 'allow' => ['/abcd']]]);

		$this->assertSame("User-agent: *\nAllow: /abcd\nDisallow: /abcd\n", $rendered);
	}

	public function testEqualRulesOfOneDirectiveSortAlphabetically() {
		$rendered = $this->render(['*' => ['disallow' => ['/bbb', '/aaa', '/ccc']]]);

		$this->assertSame("User-agent: *\nDisallow: /aaa\nDisallow: /bbb\nDisallow: /ccc\n", $rendered);
	}

	public function testNamedAgentsComeFirstAndTheCatchAllLast() {
		$rendered = $this->render([
			'*'       => ['disallow' => ['/admin']],
			'Zbot'    => ['disallow' => ['/z']],
			'apibot'  => ['disallow' => ['/a']],
		]);

		$this->assertSame(
			"User-agent: apibot\nDisallow: /a\n\nUser-agent: Zbot\nDisallow: /z\n\nUser-agent: *\nDisallow: /admin\n",
			$rendered
		);
	}

	/** Two agents with the same rules mean one group with two headers. */
	public function testAgentsSharingRulesShareAGroup() {
		$rendered = $this->render([
			'bingbot'    => ['disallow' => ['/']],
			'duckduckgo' => ['disallow' => ['/']],
		]);

		$this->assertSame("User-agent: bingbot\nUser-agent: duckduckgo\nDisallow: /\n", $rendered);
	}

	public function testSharedAgentsAreSortedWithinTheGroup() {
		$rendered = $this->render([
			'zbot' => ['disallow' => ['/']],
			'*'    => ['disallow' => ['/']],
			'abot' => ['disallow' => ['/']],
		]);

		$this->assertSame(
			"User-agent: abot\nUser-agent: zbot\nUser-agent: *\nDisallow: /\n",
			$rendered
		);
	}

	public function testDropsAndLogsARuleThatIsNotAPath() {
		$rendered = $this->render(['*' => ['disallow' => ['Host: www.example.com', '/ok']]]);

		$this->assertSame("User-agent: *\nDisallow: /ok\n", $rendered);
		$this->assertLogged('disallow "Host: www.example.com" dropped for *: a rule has to be a path.');
	}

	/** A "#" would be read back as the start of a comment. */
	public function testDropsAndLogsARuleThatWouldNotSurviveAReparse() {
		$rendered = $this->render(['*' => ['disallow' => ["/a#b", "/c\nd", '/ok']]]);

		$this->assertSame("User-agent: *\nDisallow: /ok\n", $rendered);
		$this->assertLogged('disallow "/a#b" dropped for *: a rule has to be a path.');
	}

	/** The processors already deduplicate; this is what keeps a hand-built tree honest. */
	public function testWritesARepeatedRuleOnce() {
		$this->assertSame(
			"User-agent: *\nDisallow: /admin\n",
			$this->render(['*' => ['disallow' => ['/admin', '/admin']]])
		);
	}

	public function testTrimsValues() {
		$rendered = $this->render([' * ' => ['disallow' => ['  /admin  ']]]);

		$this->assertSame("User-agent: *\nDisallow: /admin\n", $rendered);
	}

	public function testDropsAValueThatIsNotAString() {
		$this->assertSame(
			"User-agent: *\nDisallow: /ok\n",
			$this->render(['*' => ['disallow' => [['/nested'], '/ok']]])
		);
	}

	/** A scalar where the tree usually holds a list is still renderable. */
	public function testAcceptsAScalarWhereAListIsUsual() {
		$this->assertSame("User-agent: *\nDisallow: /admin\n", $this->render(['*' => ['disallow' => '/admin']]));
	}

	public function testWholeSecondDelaysRenderWithoutADecimalPart() {
		$rendered = $this->render(['*' => ['disallow' => ['/'], 'crawl-delay' => 5.0, 'cache-delay' => 10]]);

		$this->assertSame("User-agent: *\nDisallow: /\nCrawl-delay: 5\nCache-delay: 10\n", $rendered);
	}

	public function testFractionalDelaysKeepTheirDecimalPart() {
		$rendered = $this->render(['*' => ['disallow' => ['/'], 'crawl-delay' => 2.5]]);

		$this->assertStringContainsString("Crawl-delay: 2.5", $rendered);
	}

	/**
	 * @dataProvider provideUnusableDelays
	 */
	public function testDropsAndLogsAnUnusableDelay(mixed $delay) {
		$rendered = $this->render(['*' => ['disallow' => ['/'], 'crawl-delay' => $delay]]);

		$this->assertSame("User-agent: *\nDisallow: /\n", $rendered);
		$this->assertLogged('crawl-delay dropped for * as not a positive number.');
	}

	public function provideUnusableDelays(): array {
		return [
			'not a number' => ['soon'],
			'negative'     => [-1],
			'not a scalar' => [['5']],
		];
	}

	public function testHostIsHoistedOutOfTheGroup() {
		$rendered = $this->render(['*' => ['disallow' => ['/'], 'host' => 'example.com']]);

		$this->assertSame("User-agent: *\nDisallow: /\n\nHost: example.com\n", $rendered);
	}

	public function testDropsAndLogsAnInvalidHost() {
		$rendered = $this->render(['*' => ['disallow' => ['/'], 'host' => 'https://example.com']]);

		$this->assertSame("User-agent: *\nDisallow: /\n", $rendered);
		$this->assertLogged('Host "https://example.com" dropped for * as not a valid hostname.');
	}

	public function testDropsAndLogsAnIpAddressHost() {
		$this->render(['*' => ['disallow' => ['/'], 'host' => '192.168.0.1']]);

		$this->assertLogged('Host "192.168.0.1" dropped for * as not a valid hostname.');
	}

	/** Host describes the file, so a second one cannot also be true. */
	public function testKeepsTheFirstOfSeveralHostsAndWarns() {
		$rendered = $this->render([
			'*'    => ['disallow' => ['/'], 'host' => 'example.com'],
			'abot' => ['disallow' => ['/a'], 'host' => 'other.example.com'],
		]);

		$this->assertSame(1, substr_count($rendered, 'Host: '), $rendered);
		$this->assertStringContainsString('Host: example.com', $rendered);
		$this->assertLogged('Several hosts found (example.com, other.example.com)');
	}

	public function testRepeatsOfOneHostAreNotAConflict() {
		$rendered = $this->render([
			'*'    => ['disallow' => ['/'], 'host' => 'example.com'],
			'abot' => ['disallow' => ['/a'], 'host' => 'example.com'],
		]);

		$this->assertSame(1, substr_count($rendered, 'Host: example.com'), $rendered);
		$this->assertFalse($this->logged('Several hosts found'));
	}

	public function testSitemapsAreHoistedAndDeduplicated() {
		$rendered = $this->render([
			'*'    => ['disallow' => ['/'], 'sitemap' => ['https://example.com/s.xml']],
			'abot' => ['disallow' => ['/a'], 'sitemap' => ['https://example.com/s.xml', 'https://example.com/s2.xml']],
		]);

		$this->assertStringEndsWith(
			"\nSitemap: https://example.com/s.xml\nSitemap: https://example.com/s2.xml\n",
			$rendered
		);
	}

	/**
	 * @dataProvider provideInvalidSitemaps
	 */
	public function testDropsAndLogsAnInvalidSitemap(string $sitemap) {
		$rendered = $this->render(['*' => ['disallow' => ['/'], 'sitemap' => [$sitemap]]]);

		$this->assertSame("User-agent: *\nDisallow: /\n", $rendered);
		$this->assertLogged("Sitemap \"{$sitemap}\" dropped for *: an absolute URL is required.");
	}

	public function provideInvalidSitemaps(): array {
		return [
			'relative'      => ['/sitemap.xml'],
			'no host'       => ['https:///sitemap.xml'],
			'unknown scheme'=> ['gopher://example.com/s.xml'],
			'host invalid'  => ['https://-example-/s.xml'],
			'unparseable'   => ['https://exa mple.com:port/s.xml'],
			'commented'     => ['https://example.com/s.xml#frag'],
		];
	}

	public function testCleanParamsAreRenderedInTheirOwnBlock() {
		$rendered = $this->render([
			'*'           => ['disallow' => ['/']],
			'clean-param' => ['/public/users' => ['token', 'uid'], '/*' => ['sid']],
		]);

		$this->assertSame(
			"User-agent: *\nDisallow: /\n\nClean-param: token&uid /public/users\nClean-param: sid /*\n",
			$rendered
		);
	}

	public function testDropsAndLogsCleanParamsThatAreNotAMap() {
		$rendered = $this->render(['*' => ['disallow' => ['/']], 'clean-param' => 'token /path']);

		$this->assertSame("User-agent: *\nDisallow: /\n", $rendered);
		$this->assertLogged('Clean-param for "0" dropped as invalid.');
	}

	public function testDropsAndLogsCleanParamsWithoutAPath() {
		$this->render(['*' => ['disallow' => ['/']], 'clean-param' => ['users' => ['token']]]);

		$this->assertLogged('Clean-param for "users" dropped as invalid.');
	}

	public function testDropsAndLogsCleanParamsLeftWithNoNames() {
		$this->render(['*' => ['disallow' => ['/']], 'clean-param' => ['/users' => ['not a name']]]);

		$this->assertLogged('Clean-param for "/users" dropped as invalid.');
	}

	public function testKeepsTheUsableCleanParamNamesAndWarns() {
		$rendered = $this->render([
			'*'           => ['disallow' => ['/']],
			'clean-param' => ['/users' => ['token', 'not a name', 'token']],
		]);

		$this->assertStringContainsString('Clean-param: token /users', $rendered);
		$this->assertLogged('Some Clean-param names for "/users" dropped as not param names.');
	}

	public function testDropsAndLogsAnUnknownDirective() {
		$rendered = $this->render(['*' => ['disallow' => ['/'], 'request-rate' => '1/10s']]);

		$this->assertSame("User-agent: *\nDisallow: /\n", $rendered);
		$this->assertLogged('request-rate is not a directive this library writes, dropped for *.');
	}

	/**
	 * @dataProvider provideUnusableAgentNames
	 */
	public function testDropsAndLogsAnUnusableAgentName(string $name) {
		$rendered = $this->render([$name => ['disallow' => ['/']], '*' => ['disallow' => ['/admin']]]);

		$this->assertSame("User-agent: *\nDisallow: /admin\n", $rendered);
		$this->assertLogged("User-agent \"{$name}\" dropped as not a usable name.");
	}

	public function provideUnusableAgentNames(): array {
		return [
			'empty'     => ['  '],
			'colon'     => ['bot:1'],
			'comment'   => ['bot#1'],
			'multiline' => ["bot\nother"],
		];
	}

	public function testDropsAndLogsAGroupThatIsNotAListOfDirectives() {
		$rendered = $this->render(['abot' => 'disallow', '*' => ['disallow' => ['/']]]);

		$this->assertSame("User-agent: *\nDisallow: /\n", $rendered);
		$this->assertLogged('Nothing left to write for abot, the group is dropped.');
	}

	public function testDropsAndLogsAGroupWithNothingLeftToWrite() {
		$rendered = $this->render(['abot' => [], '*' => ['disallow' => ['/']]]);

		$this->assertSame("User-agent: *\nDisallow: /\n", $rendered);
		$this->assertLogged('Nothing left to write for abot, the group is dropped.');
	}

	public function testEmptyTreeRendersNothingAndWarns() {
		$this->assertSame('', $this->render([]));
		$this->assertLogged('Nothing valid left to render, returning an empty document.');
	}

	/** A numeric agent name arrives as an int key. */
	public function testNumericAgentNamesAreRendered() {
		$this->assertSame("User-agent: 360Spider\nDisallow: /\n", $this->render(['360Spider' => ['disallow' => ['/']]]));
	}

	/** Every call starts from a clean sheet, or the second render would repeat the first one's hosts. */
	public function testFileWideDirectivesDoNotLeakBetweenRenders() {
		$this->render(['*' => ['disallow' => ['/'], 'host' => 'example.com', 'sitemap' => ['https://example.com/s.xml']]]);

		$this->assertSame("User-agent: *\nDisallow: /\n", $this->render(['*' => ['disallow' => ['/']]]));
	}

	public function testWritesUtf8WithoutConvertingOrWarning() {
		$rendered = $this->render(['Яндекс' => ['disallow' => ['/админка']]]);

		$this->assertSame("User-agent: Яндекс\nDisallow: /админка\n", $rendered);
		$this->assertFalse($this->logged('Encoding you are passing is different from UTF-8'));
	}

	/**
	 * @dataProvider provideUtf8Spellings
	 */
	public function testUtf8IsNotAConversionHoweverItIsSpelled(?string $encoding) {
		$rendered = $this->render(['*' => ['disallow' => ['/админка']]], $encoding);

		$this->assertSame("User-agent: *\nDisallow: /админка\n", $rendered);
		$this->assertFalse($this->logged('Encoding you are passing is different from UTF-8'));
	}

	public function provideUtf8Spellings(): array {
		return [
			'null'    => [null],
			'empty'   => [''],
			'dashed'  => ['UTF-8'],
			'bare'    => ['utf8'],
			'scored'  => ['utf_8'],
			'spaced'  => [' UTF-8 '],
		];
	}

	/** The tree is UTF-8, so another encoding is a conversion on the way out. */
	public function testConvertsToTheRequestedEncoding() {
		$rendered = $this->render(['*' => ['disallow' => ['/админка']]], 'Windows-1251');

		$this->assertSame(iconv('UTF-8', 'Windows-1251', "User-agent: *\nDisallow: /админка\n"), $rendered);
		$this->assertLogged('Encoding you are passing is different from UTF-8');
	}

	/** Quietly writing UTF-8 instead would serve bytes under a charset they are not in. */
	public function testRefusesAnEncodingIconvDoesNotKnow() {
		$this->expectException(EncodingFailedException::class);
		$this->expectExceptionMessage('Cannot write this robots.txt as NoSuchCharset');

		$this->render(['*' => ['disallow' => ['/admin']]], 'NoSuchCharset');
	}

	/** An arrow has no Windows-1251 spelling, so the document cannot be written as one. */
	public function testRefusesToWriteACharacterTheEncodingCannotRepresent() {
		$this->expectException(EncodingFailedException::class);

		$this->render(['*' => ['disallow' => ['/a→b']]], 'Windows-1251');
	}

	public function testThrowsWhenTheOutputWillNotTakeTheBytes() {
		$readOnly = fopen('php://memory', 'r');

		$this->expectException(WriteFailedException::class);

		try {
			$this->writer->setTree(['*' => ['disallow' => ['/admin']]])->setOutput($readOnly)->render();
		} finally {
			fclose($readOnly);
		}
	}

	/** The warning is about the encoding asked for, not about what the document held - as on the read side. */
	public function testAnEmptyDocumentStillWarnsAboutTheEncoding() {
		$this->assertSame('', $this->render([], 'Windows-1251'));
		$this->assertLogged('Encoding you are passing is different from UTF-8');
	}

	public function testRejectsAnOutputThatIsNotAStream() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Argument must be a valid resource type. string given.');

		$this->writer->setOutput('robots.txt');
	}

	public function testRefusesToRenderWithNowhereToWriteTo() {
		$this->expectException(NoOutputException::class);
		$this->expectExceptionMessage('Nowhere to write to - call setOutput() first.');

		$this->writer->setTree(['*' => ['disallow' => ['/admin']]])->render();
	}

	public function testTheSettersChain() {
		$out = fopen('php://memory', 'r+');

		$this->assertSame($this->writer, $this->writer->setTree([])->setEol("\n")->setEncoding(null)->setOutput($out));

		fclose($out);
	}

	/**
	 * Groups are keyed by a digest of their rules, so two different rule sets landing on the same
	 * digest must still render as two groups rather than silently merging.
	 */
	public function testAGroupKeyCollisionDoesNotMergeUnrelatedGroups() {
		$writer = new class extends StringWriter {
			protected function groupKey(array $lines): string {
				return 'always-the-same';
			}
		};

		$out = fopen('php://memory', 'r+');
		$writer->setTree(['abot' => ['disallow' => ['/a']], 'zbot' => ['disallow' => ['/z']]])
			->setEol("\n")
			->setOutput($out)
			->render();
		rewind($out);
		$rendered = stream_get_contents($out);
		fclose($out);

		$this->assertSame(
			"User-agent: abot\nDisallow: /a\n\nUser-agent: zbot\nDisallow: /z\n",
			$rendered
		);
	}

	/**
	 * A stream that complains but still takes the bytes: the complaint has to reach the log rather
	 * than be swallowed with the handler, which is how a dropped rule went unnoticed before.
	 */
	public function testDiagnosticsRaisedWhileWritingAreLogged() {
		stream_wrapper_register('rtp.noisy', NoisyStream::class);

		$noisy = fopen('rtp.noisy://out', 'w');
		$this->writer->setTree(['*' => ['disallow' => ['/admin']]])->setEol("\n")->setOutput($noisy)->render();
		fclose($noisy);
		stream_wrapper_unregister('rtp.noisy');

		$this->assertLogged('While writing: disk is grumpy');
	}

	/** Buffered until a logger arrives, so a writer built without one still works. */
	public function testWorksWithoutALogger() {
		$out = fopen('php://memory', 'r+');
		(new StringWriter())->setTree(['*' => ['disallow' => ['/']]])->setOutput($out)->render();
		rewind($out);

		$this->assertSame("User-agent: *\r\nDisallow: /\r\n", stream_get_contents($out));

		fclose($out);
	}
}

/** Takes every byte it is given and grumbles about it, so a test can see what the writer does with that. */
class NoisyStream {

	public $context;

	public function stream_open(string $path, string $mode, int $options, ?string &$opened): bool {
		return true;
	}

	public function stream_write(string $data): int {
		trigger_error('disk is grumpy', E_USER_WARNING);

		return strlen($data);
	}

	public function stream_flush(): bool {
		return true;
	}

	public function stream_close(): void {
	}
}
