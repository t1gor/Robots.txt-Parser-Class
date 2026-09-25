<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser;

enum Directive: string {

	/**
	 * @link https://yandex.com/support/webmaster/robot-workings/allow-disallow.html#allow-disallow
	 */
	case ALLOW = 'allow';
	case DISALLOW = 'disallow';

	case HOST = 'host';

	/**
	 * @link https://yandex.com/support/webmaster/robot-workings/sitemap.html#sitemap
	 */
	case SITEMAP = 'sitemap';

	/**
	 * @link https://yandex.com/support/webmaster/robot-workings/user-agent.html#user-agent
	 */
	case USERAGENT = 'user-agent';

	/** Never appears in a file: an alias {@see RobotsTxtParser::getDelay()} accepts for CACHE_DELAY. */
	case CACHE = 'cache';
	case CACHE_DELAY = 'cache-delay';

	/**
	 * @link https://yandex.com/support/webmaster/robot-workings/clean-param.html#clean-param
	 */
	case CLEAN_PARAM = 'clean-param';

	/**
	 * @link https://yandex.com/support/webmaster/robot-workings/crawl-delay.html#crawl-delay
	 */
	case CRAWL_DELAY = 'crawl-delay';

	/**
	 * Extended standard directives
	 *
	 * @link http://www.conman.org/people/spc/robots2.html
	 */
	case REQUEST_RATE = 'request-rate';
	case VISIT_TIME = 'visit-time';
	case ROBOT_VERSION = 'robot-version';
	case COMMENT = 'comment';

	/**
	 * Keeps a path out of the index without keeping the crawler out of it.
	 *
	 * @link https://yandex.com/support/webmaster/controlling-robot/html.html
	 */
	case NOINDEX = 'noindex';

	/**
	 * Names a file may actually carry, so CACHE is excluded - it is only an argument alias, and
	 * listing it would make SkipUnsupportedDirectivesFilter start keeping "Cache:" lines.
	 *
	 * @return string[]
	 */
	public static function getAll(): array {
		return array_map(
			fn (self $directive): string => $directive->value,
			array_filter(self::cases(), fn (self $directive): bool => self::CACHE !== $directive)
		);
	}

	/** Whether a user-agent may carry several of these, rather than the last one winning. */
	public function isRepeatable(): bool {
		return match ($this) {
			self::ALLOW, self::DISALLOW, self::NOINDEX,
			self::SITEMAP, self::REQUEST_RATE, self::COMMENT => true,
			default                                          => false,
		};
	}

	/** How the directive is written into a robots.txt: "User-agent", "Clean-param". */
	public function label(): string {
		// no case carries a multibyte name
		return ucfirst($this->value);
	}

	public static function getRegex(): string {
		return "/^(?!(" . implode('|', self::getAll()) . ")\s*:+).+/mui";
	}

	/**
	 * Whitespace sits inside the lookahead: outside it the engine could match none of it and the
	 * lookahead would always succeed, which dropped every Request-rate line there is.
	 */
	public static function getRequestRateRegex(): string {
		return "/^" . self::REQUEST_RATE->value . ":+(?![^\S\r\n]*[0-9]+\/[0-9]+).*/mui";
	}

	public static function getCrawlDelayRegex(): string {
		return "/^" . self::CRAWL_DELAY->value . ":+\s*(\D+)$/mui";
	}

	/**
	 * Allow/Disallow values are path patterns, so they start with "/". An empty value is
	 * legal though - "Disallow:" means "nothing is disallowed" - so it is left alone.
	 *
	 * @link https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2
	 */
	public static function getAllowDisallowRegex(): string {
		// possessive: giving a colon back read "Disallow::/x" as a value of ":/x", so it was dropped
		return "/^(" . self::ALLOW->value . "|" . self::DISALLOW->value . "):++[^\S\\r\\n]*+(?![\/\s])\S.*$/mui";
	}

	public static function attemptGetInline(string $rule): string|false {
		// lowercased once, not once per directive
		$haystack = mb_strtolower($rule);

		foreach (self::getAll() as $directive) {
			if (str_starts_with($haystack, $directive . ':')) {
				return $directive;
			}
		}

		return false;
	}

	public static function stripInline(string $rule): string {
		$directive = self::attemptGetInline($rule);

		if ($directive !== false) {
			$rule = trim(str_ireplace($directive . ':', '', $rule));
		}

		return $rule;
	}
}
