<?php declare(strict_types=1);

namespace Parser;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Parser\Url;

/**
 * @covers \t1gor\RobotsTxtParser\Parser\Url::getPath
 * @covers \t1gor\RobotsTxtParser\Parser\Url::isValidScheme
 */
class UrlTest extends TestCase {

	/**
	 * @dataProvider pathProvider
	 */
	public function testGetPath(string $url, string $expected) {
		$this->assertSame($expected, (new Url($url))->getPath());
	}

	public function pathProvider(): array {
		return [
			'plain path'        => ['/catalog/', '/catalog/'],
			'http'              => ['http://example.com/catalog/', '/catalog/'],
			'https'             => ['https://example.com/catalog/auto', '/catalog/auto'],
			'ftp'               => ['ftp://example.com/pub', '/pub'],
			'sftp'              => ['sftp://example.com/pub', '/pub'],
			'explicit port'     => ['http://example.com:8080/catalog/', '/catalog/'],
			'no path'           => ['http://example.com', '/'],
			'query is kept'     => ['http://example.com/search?q=1&p=2', '/search?q=1&p=2'],
			'fragment dropped'  => ['http://example.com/page#anchor', '/page'],
			'unsupported scheme' => ['mailto:someone@example.com', 'mailto:someone@example.com'],
		];
	}

	/**
	 * getservbyname() reads /etc/services; hosts without it (slim containers, for one) used to
	 * fail the whole URL, leaving getPath() to hand rules the full URL to match against.
	 */
	public function testDefaultPortDoesNotDependOnEtcServices() {
		$this->assertSame('/catalog/', (new Url('http://example.com/catalog/'))->getPath());

		foreach (array_keys(Url::DEFAULT_PORTS) as $scheme) {
			$this->assertTrue(Url::isValidScheme($scheme), "{$scheme} is supported");
		}

		$this->assertFalse(Url::isValidScheme('mailto'));
	}
}
