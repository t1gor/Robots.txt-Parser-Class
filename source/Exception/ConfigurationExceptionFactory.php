<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Exception;

/**
 * Wording for every way a configuration value can be unusable.
 *
 * For most callers this message is the only feedback there is - Laravel and WordPress validate
 * nothing of their own - so each one names the option, what arrived, and what was expected.
 */
final class ConfigurationExceptionFactory {

	/**
	 * @param string[] $known
	 */
	public static function unknownOption(string $given, array $known): ConfigurationException {
		$suggestion = self::closest($given, $known);

		return new ConfigurationException(strtr('Unknown configuration option "{given}". {hint}', [
			'{given}' => $given,
			'{hint}'  => is_null($suggestion)
				? 'Known options: ' . implode(', ', $known) . '.'
				: sprintf('Did you mean "%s"?', $suggestion),
		]));
	}

	public static function notAByteCount(string $option, mixed $given): ConfigurationException {
		return new ConfigurationException(sprintf(
			'Configuration option "%s" must be an integer, an integer string, "none"/"unlimited" or null; got %s.',
			$option,
			is_scalar($given) ? var_export($given, true) : get_debug_type($given)
		));
	}

	public static function notPositive(string $option, int $given): ConfigurationException {
		return new ConfigurationException(sprintf(
			'Configuration option "%s" must be a positive number of bytes, or null to disable the limit; got %d.',
			$option,
			$given
		));
	}

	/**
	 * @param string[] $known
	 */
	private static function closest(string $given, array $known): ?string {
		$best     = null;
		$distance = PHP_INT_MAX;

		foreach ($known as $option) {
			$current = levenshtein($given, $option);

			if ($current < $distance) {
				$distance = $current;
				$best     = $option;
			}
		}

		// far enough away and a suggestion is just noise
		return $distance <= (int) ceil(mb_strlen($given) / 2) ? $best : null;
	}
}
