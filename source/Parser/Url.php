<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

use League\Uri\Exceptions\SyntaxError;
use League\Uri\UriString;

/**
 * A URL reduced to the string rules are matched against. Immutable: everything is worked out in
 * the constructor, so repeated checks against the same URL cost nothing.
 */
class Url {

	/**
	 * Supported schemes and the port each one defaults to. league/uri happily parses any RFC-valid
	 * scheme, which is wider than robots.txt needs, so this doubles as the whitelist. The ports
	 * themselves play no part in matching - they document what each scheme implies.
	 */
	const DEFAULT_PORTS = [
		'http'  => 80,
		'https' => 443,
		'ftp'   => 21,
		'sftp'  => 22,
	];

	/**
	 * Everything that survives encoding untouched: RFC 3986 unreserved (§2.3) and reserved (§2.2),
	 * plus '%' itself. Every other byte becomes a percent-encoded triplet.
	 */
	private const REGEX_NEEDS_ENCODING = '/[^A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/';

	private string $encoded;
	private ?string $path;

	public function __construct(string $in) {
		$this->encoded = static::encode(trim($in));
		$this->path    = $this->parse($this->encoded);
	}

	/**
	 * URL encoder according to RFC 3986: disallowed characters are converted to their percentage
	 * encodings, the reserved ones are left as written.
	 *
	 * Rules are encoded through here too: RFC 9309 requires both sides of a comparison
	 * to be percent-encoded first.
	 *
	 * Deliberately not league/uri's Encoder: its encodePath() also escapes '#', '?', '[', ']' and
	 * a bare '%', which would both break the path+'?'+query string getPath() hands back and
	 * double-encode any rule containing a literal '%'. Encoding has to be idempotent here, because
	 * a rule is encoded per wildcard-separated literal and a path is encoded on every check.
	 *
	 * @link https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2
	 * @link https://www.rfc-editor.org/rfc/rfc3986#section-2
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

	/**
	 * Path + query as one string - what rules are matched against - or null when the input is not
	 * a URL we can reduce to a path.
	 */
	private function parse(string $url): ?string {
		try {
			$parsed = UriString::parse($url);
		} catch (SyntaxError) {
			// malformed authority, unparseable port, IP-literal host that is not one
			return null;
		}

		// casts fold "missing" into "invalid" - both leave us without a path to match on
		if (!static::isValidScheme((string) $parsed['scheme']) || !HostName::isValid((string) $parsed['host'])) {
			return null;
		}

		// an authority-only URL means the root; the fragment plays no part in matching
		return ('' === $parsed['path'] ? '/' : $parsed['path'])
			. (null === $parsed['query'] ? '' : '?' . $parsed['query']);
	}

	/** False means getPath() hands back the whole URL, because it did not reduce to a path. */
	public function isReducedToPath(): bool {
		return null !== $this->path;
	}

	public function getPath(): string {
		return $this->path ?? $this->encoded;
	}
}
