<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser;

abstract class Directive {

	/**
	 * @link https://yandex.com/support/webmaster/robot-workings/allow-disallow.html#allow-disallow
	 */
	const ALLOW = 'allow';
	const DISALLOW = 'disallow';

	const HOST = 'host';

	/**
	 * @link https://yandex.com/support/webmaster/robot-workings/sitemap.html#sitemap
	 */
	const SITEMAP = 'sitemap';

	/**
	 * @link https://yandex.com/support/webmaster/robot-workings/user-agent.html#user-agent
	 */
	const USERAGENT = 'user-agent';
	const CACHE = 'cache';
	const CACHE_DELAY = 'cache-delay';

	/**
	 * @link https://yandex.com/support/webmaster/robot-workings/clean-param.html#clean-param
	 */
	const CLEAN_PARAM = 'clean-param';

	/**
	 * @link https://yandex.com/support/webmaster/robot-workings/crawl-delay.html#crawl-delay
	 */
	const CRAWL_DELAY = 'crawl-delay';

	/**
	 * Extended standard directives
	 *
	 * @link http://www.conman.org/people/spc/robots2.html
	 */
	const REQUEST_RATE = 'request-rate';
	const VISIT_TIME = 'visit-time';

	public static function getAll(): array {
		return [
			self::ALLOW,
			self::DISALLOW,
			self::HOST,
			self::SITEMAP,
			self::USERAGENT,
			self::CRAWL_DELAY,
			self::CACHE_DELAY,
			self::CLEAN_PARAM,
			self::REQUEST_RATE,
			self::VISIT_TIME,
		];
	}

	public static function getRegex(): string {
		return "/^(?!(" . implode('|', Directive::getAll()) . ")\s*:+).+/mui";
	}

	public static function getRequestRateRegex(): string {
		return "/^" . self::REQUEST_RATE . ":+\s*(?![0-9]+\/[0-9]+).*/mui";
	}

	public static function getCrawlDelayRegex(): string {
		return "/^" . self::CRAWL_DELAY . ":+\s*(\D+)$/mui";
	}

	/**
	 * @TODO finish me
	 * @return string
	 */
	public static function getAllowDisallowRegex(): string {
		return "/^(" . self::ALLOW . "|" . self::DISALLOW . "):+\s*\\{1}.*$/mui";
	}

	public static function attemptGetInline(string $rule) {
		foreach (static::getAll() as $directive) {
			if (0 === strpos(mb_strtolower($rule), $directive . ':')) {
				return $directive;
			}
		}
		return false;
	}

	public static function stripInline(string $rule): string {
		$directive = static::attemptGetInline($rule);

		if ($directive !== false) {
			$rule = trim(str_ireplace($directive . ':', '', $rule));
		}

		return $rule;
	}
}
