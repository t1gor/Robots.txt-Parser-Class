<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser;

use t1gor\RobotsTxtParser\Exception\ConfigurationException;
use t1gor\RobotsTxtParser\Exception\ConfigurationExceptionFactory;

/**
 * Parser options.
 *
 * Immutable on purpose: the reader bounds its read when it is built, so a limit that could change
 * afterwards would disagree with the bytes already copied.
 *
 * Warnings are collected rather than logged - a Configuration is usually built long before a logger
 * exists (a Laravel service provider, a Symfony factory, wp-config.php), so the parser replays them
 * once it has one.
 *
 * @see \t1gor\RobotsTxtParser\Config\ConfigurationFactory to build one from arrays or the environment.
 *
 * @link https://www.rfc-editor.org/rfc/rfc9309#section-2.5
 */
final readonly class Configuration {

	/** Both Google and RFC 9309 land on 500 KiB. */
	const DEFAULT_BYTE_LIMIT = 500 * 1024;

	/** Not a floor - below this we warn, because the file likely truncates to nothing. */
	const RECOMMENDED_MIN_BYTE_LIMIT = 24 * 1024;

	const OPTION_BYTE_LIMIT = 'byte_limit';

	/** @var array<int, array{message: string, context: array<string, mixed>}> */
	public array $warnings;

	/**
	 * @param int|null $byteLimit Bytes to read at most, or null for no limit.
	 *
	 * @throws ConfigurationException on zero or negative limits
	 */
	public function __construct(public ?int $byteLimit = self::DEFAULT_BYTE_LIMIT) {
		$warnings = [];

		if (is_null($byteLimit)) {
			$warnings[] = [
				'message' => 'Byte limit is disabled: the whole robots.txt will be read however large it '
					. 'is, risking memory and CPU exhaustion on a hostile or runaway file.',
				'context' => [self::OPTION_BYTE_LIMIT => null],
			];
		} elseif ($byteLimit <= 0) {
			// "read nothing" is never meaningful, and 0 is what people reach for when they mean null
			throw ConfigurationExceptionFactory::notPositive(self::OPTION_BYTE_LIMIT, $byteLimit);
		} elseif ($byteLimit < self::RECOMMENDED_MIN_BYTE_LIMIT) {
			$warnings[] = [
				'message' => 'Byte limit {byte_limit} is below the recommended minimum {recommended}: '
					. 'robots.txt may be truncated to nothing, and unmatched paths default to allowed.',
				'context' => [
					self::OPTION_BYTE_LIMIT => $byteLimit,
					'recommended'           => self::RECOMMENDED_MIN_BYTE_LIMIT,
				],
			];
		}

		$this->warnings = $warnings;
	}
}
