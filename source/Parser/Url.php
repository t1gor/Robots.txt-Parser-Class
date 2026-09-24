<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

/** A URL reduced to the string rules are matched against. Immutable. */
class Url {

	/** Also the scheme whitelist; the ports play no part in matching. */
	const DEFAULT_PORTS = [
		'http'  => 80,
		'https' => 443,
		'ftp'   => 21,
		'sftp'  => 22,
	];

	/** RFC 3986 unreserved and reserved, plus '%'. Everything else gets encoded. */
	private const REGEX_NEEDS_ENCODING = '/[^A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/';

	private string $encoded;
	private ?string $path;

	public function __construct(string $in) {
		$this->encoded = static::encode(trim($in));
		$this->path    = $this->parse($this->encoded);
	}

	/**
	 * Rules come through here too: RFC 9309 compares both sides percent-encoded.
	 *
	 * Must stay idempotent - rules are encoded per literal, paths on every check - which is why
	 * '%' is left alone, and '?' must survive for the path+query getPath() returns.
	 *
	 * @link https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2
	 */
	public static function encode(string $url): string {
		return preg_replace_callback(
			self::REGEX_NEEDS_ENCODING,
			fn (array $matched): string => rawurlencode($matched[0]),
			$url
		);
	}

	public static function isValidScheme(string $scheme): bool {
		return isset(self::DEFAULT_PORTS[$scheme]);
	}

	/** Path + query, or null when the input does not reduce to a path. */
	private function parse(string $url): ?string {
		$parsed = parse_url($url);

		if (false === $parsed || !isset($parsed['scheme'], $parsed['host'])) {
			return null;
		}

		if (!static::isValidScheme($parsed['scheme']) || !HostName::isValid($parsed['host'])) {
			return null;
		}

		// authority-only means the root; the fragment never matters for matching
		return ($parsed['path'] ?? '/')
			. (isset($parsed['query']) ? '?' . $parsed['query'] : '');
	}

	/** False means getPath() returns the whole URL instead of a path. */
	public function isReducedToPath(): bool {
		return null !== $this->path;
	}

	public function getPath(): string {
		return $this->path ?? $this->encoded;
	}
}
