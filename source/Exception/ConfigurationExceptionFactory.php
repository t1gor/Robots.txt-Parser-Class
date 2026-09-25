<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Exception;

use t1gor\RobotsTxtParser\Config\Option;

/**
 * Wording for every way a configuration value can be unusable. Laravel and WordPress validate
 * nothing of their own, so for most callers this message is the only feedback there is.
 */
final class ConfigurationExceptionFactory {

	/**
	 * @param string[] $known
	 */
	public static function unknownOption(string $given, array $known): UnknownOptionException {
		return new UnknownOptionException(sprintf(
			'Unknown configuration option "%s". Known options: %s.',
			$given,
			implode(', ', $known)
		));
	}

	public static function notAByteCount(Option $option, mixed $given): InvalidByteCountException {
		return new InvalidByteCountException(sprintf(
			'Configuration option "%s" must be an integer, an integer string, "none"/"unlimited" or null; got %s.',
			$option->value,
			is_scalar($given) ? var_export($given, true) : get_debug_type($given)
		));
	}

	public static function notAnEncoding(Option $option, mixed $given): InvalidEncodingException {
		return new InvalidEncodingException(sprintf(
			'Configuration option "%s" must be a charset name or null; got %s.',
			$option->value,
			is_scalar($given) ? var_export($given, true) : get_debug_type($given)
		));
	}

	public static function notPositive(Option $option, int $given): ByteCountOutOfRangeException {
		return new ByteCountOutOfRangeException(sprintf(
			'Configuration option "%s" must be a positive number of bytes, or null to disable the limit; got %d.',
			$option->value,
			$given
		));
	}
}
