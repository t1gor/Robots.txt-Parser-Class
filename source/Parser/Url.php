<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

use Psr\Log\LoggerAwareInterface;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;

class Url implements LoggerAwareInterface {

	use LogsIfAvailableTrait;

	protected string $in = '';

	public function __construct(string $in) {
		$this->in = static::encode(trim($in));
	}

	/**
	 * URL encoder according to RFC 3986
	 * Returns a string containing the encoded URL with disallowed characters converted to their percentage encodings.
	 *
	 * Rules are encoded through here too: RFC 9309 requires both sides of a comparison
	 * to be percent-encoded first.
	 *
	 * @link http://publicmind.in/blog/url-encoding/
	 * @link https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public static function encode(string $url): string {
		$reserved = [
			':' => '!%3A!ui',
			'/' => '!%2F!ui',
			'?' => '!%3F!ui',
			'#' => '!%23!ui',
			'[' => '!%5B!ui',
			']' => '!%5D!ui',
			'@' => '!%40!ui',
			'!' => '!%21!ui',
			'$' => '!%24!ui',
			'&' => '!%26!ui',
			"'" => '!%27!ui',
			'(' => '!%28!ui',
			')' => '!%29!ui',
			'*' => '!%2A!ui',
			'+' => '!%2B!ui',
			',' => '!%2C!ui',
			';' => '!%3B!ui',
			'=' => '!%3D!ui',
			'%' => '!%25!ui',
		];

		return preg_replace(array_values($reserved), array_keys($reserved), rawurlencode($url));
	}

	/**
	 * Supported schemes and the port each one defaults to. getservbyname() reads /etc/services,
	 * which slim containers do not ship - a missing entry used to invalidate the whole URL.
	 */
	const DEFAULT_PORTS = [
		'http'  => 80,
		'https' => 443,
		'ftp'   => 21,
		'sftp'  => 22,
	];

	public static function isValidScheme(string $scheme): bool {
		return isset(self::DEFAULT_PORTS[$scheme]);
	}

	/**
	 * Parse URL
	 *
	 * @param string $url
	 *
	 * @return array|false
	 */
	protected function parse(string $url) {
		$parsed = parse_url($url);

		if ($parsed === false) {
			$this->log("Failed to parse URL from {$url}");

			return false;
		}

		if (!isset($parsed['scheme']) || !static::isValidScheme($parsed['scheme'])) {
			$this->log("URL scheme invalid or missing for {$url}");

			return false;
		}

		if (!isset($parsed['host']) || !HostName::isValid($parsed['host'])) {
			$this->log("URL host invalid or missing for {$url}");

			return false;
		}

		if (!isset($parsed['port'])) {
			$port = getservbyname($parsed['scheme'], 'tcp');

			// the scheme is known to be valid by now, so the fallback always resolves
			$parsed['port'] = is_int($port) ? $port : self::DEFAULT_PORTS[$parsed['scheme']];
		}

		$parsed['custom'] = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

		return $parsed;
	}

	public function getPath() {
		$parsed = $this->parse($this->in);

		if ($parsed !== false) {
			return $parsed['custom'];
		}

		return $this->in;
	}
}
