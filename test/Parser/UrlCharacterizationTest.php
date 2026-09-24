<?php declare(strict_types=1);

namespace Parser;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Parser\Url;

/**
 * Byte-for-byte snapshot of Url. Rules share this encoder with paths, so any drift here flips
 * allow/disallow outcomes - hence pinning characters rather than describing them.
 *
 * @link https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2
 *
 * @covers \t1gor\RobotsTxtParser\Parser\Url
 */
class UrlCharacterizationTest extends TestCase {

	/** RFC 3986 §2.3 */
	const UNRESERVED = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_.~';

	/** RFC 3986 §2.2: gen-delims + sub-delims */
	const RESERVED = ":/?#[]@!$&'()*+,;=";

	/** Exhaustive over all 256 bytes, so no replacement can re-encode one quietly. */
	public function testEveryByteIsEncodedTheSameWayAsBefore() {
		$passthrough = self::UNRESERVED . self::RESERVED . '%';

		for ($byte = 0; $byte < 256; $byte++) {
			$char     = chr($byte);
			$expected = str_contains($passthrough, $char) ? $char : sprintf('%%%02X', $byte);

			$this->assertSame($expected, Url::encode($char), sprintf('byte %d (0x%02X)', $byte, $byte));
		}
	}

	/** @dataProvider unreservedProvider */
	public function testUnreservedCharactersSurviveVerbatim(string $char) {
		$this->assertSame($char, Url::encode($char));
	}

	public function unreservedProvider(): array {
		return array_map(fn (string $c): array => [$c], str_split(self::UNRESERVED));
	}

	/**
	 * Reserved characters survive encoding - rules like `/out?url=https://x/` need them.
	 *
	 * @dataProvider reservedProvider
	 */
	public function testReservedCharactersAreDecodedBackAfterEncoding(string $char) {
		$this->assertSame($char, Url::encode($char));
	}

	public function reservedProvider(): array {
		return array_map(fn (string $c): array => [$c], str_split(self::RESERVED . '%'));
	}

	/** @dataProvider encodedCharacterProvider */
	public function testCharactersOutsideTheAllowedSetAreEncoded(string $char, string $expected) {
		$this->assertSame($expected, Url::encode($char));
	}

	public function encodedCharacterProvider(): array {
		return [
			'space'          => [' ', '%20'],
			'double quote'   => ['"', '%22'],
			'less than'      => ['<', '%3C'],
			'greater than'   => ['>', '%3E'],
			'backslash'      => ['\\', '%5C'],
			'caret'          => ['^', '%5E'],
			'backtick'       => ['`', '%60'],
			'brace open'     => ['{', '%7B'],
			'brace close'    => ['}', '%7D'],
			'pipe'           => ['|', '%7C'],
			'newline'        => ["\n", '%0A'],
			'carriage ret'   => ["\r", '%0D'],
			'tab'            => ["\t", '%09'],
			'null byte'      => ["\0", '%00'],
			'del'            => ["\x7F", '%7F'],
			'high byte'      => ["\xFF", '%FF'],
		];
	}

	/** @dataProvider stringProvider */
	public function testEncode(string $in, string $expected) {
		$this->assertSame($expected, Url::encode($in));
	}

	public function stringProvider(): array {
		return [
			'empty string'           => ['', ''],
			'plain path'             => ['/catalog/', '/catalog/'],
			'utf-8 latin'            => ['café', 'caf%C3%A9'],
			'utf-8 cyrillic'         => ['каталог', '%D0%BA%D0%B0%D1%82%D0%B0%D0%BB%D0%BE%D0%B3'],
			'utf-8 cjk'              => ['日本語', '%E6%97%A5%E6%9C%AC%E8%AA%9E'],
			'utf-8 astral'           => ['🐙', '%F0%9F%90%99'],
			'spaces inside path'     => ['/path with space/', '/path%20with%20space/'],
			'query is left alone'    => ['/search?q=a+b&x=%20', '/search?q=a+b&x=%20'],
			'full url'               => ['http://example.com/catalog/', 'http://example.com/catalog/'],
			'url with fragment'      => ['https://example.com/a/b?c=d#e', 'https://example.com/a/b?c=d#e'],
			'colons in path'         => ['/path:with:colon', '/path:with:colon'],
			'nested url in query'    => ['/out?url=https://example.com/', '/out?url=https://example.com/'],
			'at symbol'              => ['/url_containing_@_symbol', '/url_containing_@_symbol'],
			'regex metachars kept'   => ['/pri$ce/b[c]/u+v/a.b/d)e/path(foo', '/pri$ce/b[c]/u+v/a.b/d)e/path(foo'],
			'regex metachars gone'   => ['/x|y/p^q/a{2}/s\\t', '/x%7Cy/p%5Eq/a%7B2%7D/s%5Ct'],
			'wildcard survives'      => ['/café/*/photo', '/caf%C3%A9/*/photo'],
			// '%' passes through, so anything percent-encoded stays as written
			'triplet uppercase hex'  => ['%2F', '%2F'],
			'triplet lowercase hex'  => ['%2f', '%2f'],
			'triplet space'          => ['%20', '%20'],
			'triplet alnum'          => ['%41', '%41'],
			'pre-encoded utf-8'      => ['/caf%C3%A9', '/caf%C3%A9'],
			'double encoded'         => ['%2520', '%2520'],
			'percent literal'        => ['%', '%'],
			'doubled percent'        => ['%%', '%%'],
			'trailing percent'       => ['100%', '100%'],
			'invalid triplet'        => ['%zz', '%zz'],
			'percent 25'             => ['%25', '%25'],
		];
	}

	/**
	 * Rules are encoded per literal, so encoding twice must be a no-op.
	 *
	 * @dataProvider idempotencyProvider
	 */
	public function testEncodingIsIdempotent(string $in) {
		$once = Url::encode($in);

		$this->assertSame($once, Url::encode($once), "encoding {$in} twice must not change it");
	}

	public function idempotencyProvider(): array {
		$cases = [
			'café', '/a b', '%2f', '%2F', '%zz', '%', '%%', '%C3%A9', '%2520', '/x|y', "\xFF", '/каталог',
		];

		return array_combine($cases, array_map(fn (string $c): array => [$c], $cases));
	}

	/** The constructor trims first, so surrounding whitespace never becomes %20. */
	public function testConstructorTrimsBeforeEncoding() {
		$this->assertSame('/spaced', (new Url('  /spaced  '))->getPath());
		$this->assertSame('', (new Url('   '))->getPath());
	}

	/** @dataProvider getPathProvider */
	public function testGetPath(string $url, string $expected) {
		$this->assertSame($expected, (new Url($url))->getPath());
	}

	/** Anything without a scheme and valid host comes back whole - that is what rules match. */
	public function getPathProvider(): array {
		return [
			// parses: path + query, fragment dropped, port ignored
			'path only'              => ['/catalog/', '/catalog/'],
			'http'                   => ['http://example.com/catalog/', '/catalog/'],
			'https'                  => ['https://example.com/catalog/auto', '/catalog/auto'],
			'ftp'                    => ['ftp://example.com/pub', '/pub'],
			'sftp'                   => ['sftp://example.com/pub', '/pub'],
			'explicit default port'  => ['http://example.com:80/p', '/p'],
			'explicit other port'    => ['http://example.com:8080/catalog/', '/catalog/'],
			'no path'                => ['http://example.com', '/'],
			'no path, scheme sftp'   => ['sftp://example.com', '/'],
			'userinfo is dropped'    => ['http://user:pass@example.com/p', '/p'],
			'query kept'             => ['http://example.com/search?q=1&p=2', '/search?q=1&p=2'],
			'query without path'     => ['http://example.com?q=1', '/?q=1'],
			'empty query kept'       => ['http://example.com/?', '/?'],
			'empty query on path'    => ['http://example.com/a?', '/a?'],
			'fragment dropped'       => ['http://example.com/page#anchor', '/page'],
			'empty fragment dropped' => ['http://example.com/#', '/'],
			'query inside fragment'  => ['http://example.com/a#b?c', '/a'],
			'encoded path preserved' => ['https://example.com/a%20b', '/a%20b'],

			// does not parse: handed back as-is
			'unsupported scheme'     => ['mailto:someone@example.com', 'mailto:someone@example.com'],
			'unknown scheme'         => ['gopher://example.com/x', 'gopher://example.com/x'],
			'file scheme'            => ['file:///etc/passwd', 'file:///etc/passwd'],
			'uppercase scheme'       => ['HTTP://example.com/Upper', 'HTTP://example.com/Upper'],
			'no host'                => ['http://', 'http://'],
			'empty host'             => ['http:///path', 'http:///path'],
			'ipv4 host'              => ['http://192.168.0.1/p', 'http://192.168.0.1/p'],
			'ipv6 host'              => ['http://[::1]/p', 'http://[::1]/p'],
			'underscore in host'     => ['http://exa_mple.com/p', 'http://exa_mple.com/p'],
			'scheme relative'        => ['//example.com/p', '//example.com/p'],
			'no scheme'              => ['example.com/p', 'example.com/p'],
			'unparseable port'       => ['http://example.com:abc/p', 'http://example.com:abc/p'],
			'empty input'            => ['', ''],
			// the host is percent-encoded by then and no longer validates
			'idn host'               => [
				'https://пример.рф/путь',
				'https://%D0%BF%D1%80%D0%B8%D0%BC%D0%B5%D1%80.%D1%80%D1%84/%D0%BF%D1%83%D1%82%D1%8C',
			],
		];
	}

	/** Only the whitelisted schemes; RFC-valid but unlisted ones are not. */
	public function testIsValidScheme() {
		foreach (array_keys(Url::DEFAULT_PORTS) as $scheme) {
			$this->assertTrue(Url::isValidScheme($scheme), "{$scheme} is supported");
		}

		foreach (['mailto', 'gopher', 'file', 'ws', 'wss', 'HTTP', 'Https', ''] as $scheme) {
			$this->assertFalse(Url::isValidScheme($scheme), "{$scheme} is not supported");
		}
	}

	/** getPath() is a pure read. */
	public function testGetPathIsRepeatable() {
		$url = new Url('http://example.com/catalog/?q=1#frag');

		$this->assertSame('/catalog/?q=1', $url->getPath());
		$this->assertSame('/catalog/?q=1', $url->getPath());
	}
}
