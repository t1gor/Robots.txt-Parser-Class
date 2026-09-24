<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser;

/**
 * Parser options and nothing else. Built and checked by {@see Config\ConfigurationFactory}.
 *
 * Immutable: the reader bounds its read when it is built, so a limit that could change afterwards
 * would disagree with the bytes already copied.
 */
final readonly class Configuration {

	/** Google and RFC 9309 both land on 500 KiB. */
	const DEFAULT_BYTE_LIMIT = 500 * 1024;

	/** A warning threshold, not a floor. */
	const RECOMMENDED_MIN_BYTE_LIMIT = 24 * 1024;

	const OPTION_BYTE_LIMIT = 'byte_limit';

	/** @param int|null $byteLimit Bytes to read at most, null for no limit. */
	public function __construct(public ?int $byteLimit = self::DEFAULT_BYTE_LIMIT) {
	}
}
