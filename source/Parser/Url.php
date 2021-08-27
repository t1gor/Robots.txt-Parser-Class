<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

use Psr\Log\LoggerAwareInterface;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;

class Url implements LoggerAwareInterface {

	use LogsIfAvailableTrait;

	protected string $in = '';

	public function __construct(string $in) {
		$this->in = $this->encode(trim($in));
	}

	/**
	 * URL encoder according to RFC 3986
	 * Returns a string containing the encoded URL with disallowed characters converted to their percentage encodings.
	 *
	 * @link http://publicmind.in/blog/url-encoding/
	 *
	 * @param string $url
	 *
	 * @return string string
	 */
	protected function encode(string $url): string {
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

	public static function isValidScheme(string $scheme): bool {
		return in_array($scheme, ['http', 'https', 'ftp', 'sftp']);
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
			$parsed['port'] = getservbyname($parsed['scheme'], 'tcp');

			if (!is_int($parsed['port'])) {
				$this->log("URL port should be a number, {$parsed['port']} found for {$url}");

				return false;
			}
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
