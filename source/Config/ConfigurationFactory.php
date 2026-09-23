<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Config;

use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\Exception\ConfigurationException;
use t1gor\RobotsTxtParser\Exception\ConfigurationExceptionFactory;

/**
 * Builds a {@see Configuration} out of whatever shape the host application keeps its settings in.
 *
 * Kept apart from Configuration itself so the value object stays a value object: it knows its own
 * invariants, this knows the formats other people write.
 */
final class ConfigurationFactory {

	/** Shared with the stream filter names. */
	const ENV_PREFIX = 'RTP_';

	/** @var string[] */
	const KNOWN_OPTIONS = [Configuration::OPTION_BYTE_LIMIT];

	/** How config files and env vars spell "read it all". */
	private const UNLIMITED_ALIASES = ['none', 'unlimited'];

	/**
	 * Every framework config layer - Laravel arrays, Symfony YAML, Yii2, Laminas, CakePHP - ends
	 * up as an array, so this is the portable entry point. Keys left out keep their defaults.
	 *
	 * @param array{byte_limit?: int|numeric-string|null} $options
	 *
	 * @throws ConfigurationException on unknown keys or unusable values
	 */
	public static function fromArray(array $options): Configuration {
		foreach (array_keys($options) as $key) {
			if (!in_array($key, self::KNOWN_OPTIONS, true)) {
				throw ConfigurationExceptionFactory::unknownOption((string) $key, self::KNOWN_OPTIONS);
			}
		}

		if (!array_key_exists(Configuration::OPTION_BYTE_LIMIT, $options)) {
			return new Configuration();
		}

		return new Configuration(self::toByteCount(
			Configuration::OPTION_BYTE_LIMIT,
			$options[Configuration::OPTION_BYTE_LIMIT]
		));
	}

	/**
	 * Reads RTP_BYTE_LIMIT from the environment, falling back to a constant of the same name -
	 * WordPress has no env convention, wp-config.php defines constants instead.
	 *
	 * @param array<string, mixed>|null $env Injectable so tests never touch the real environment.
	 *
	 * @throws ConfigurationException on unusable values
	 */
	public static function fromEnvironment(?array $env = null): Configuration {
		$name = self::ENV_PREFIX . 'BYTE_LIMIT';
		$raw  = self::readEnv($name, $env);

		// unset or blank is not a decision - keep the default
		return is_null($raw) || '' === trim($raw)
			? new Configuration()
			: new Configuration(self::toByteCount($name, $raw));
	}

	private static function readEnv(string $name, ?array $env): ?string {
		if (!is_null($env)) {
			$value = $env[$name] ?? null;
		} elseif (false !== ($fromEnv = getenv($name))) {
			$value = $fromEnv;
		} else {
			$value = defined($name) ? constant($name) : null;
		}

		return is_scalar($value) ? (string) $value : null;
	}

	/**
	 * Config layers hand over strings as often as ints: Symfony's %env()%, Laravel's env(), and
	 * every define() in wp-config.php.
	 *
	 * @throws ConfigurationException
	 */
	private static function toByteCount(string $option, mixed $value): ?int {
		if (is_null($value)) {
			return null;
		}

		// ahead of any is_scalar check - a bool would otherwise silently become 1
		if (is_int($value)) {
			return $value;
		}

		if (is_string($value)) {
			$normalised = strtolower(trim($value));

			if (in_array($normalised, self::UNLIMITED_ALIASES, true)) {
				return null;
			}

			// whole numbers only - "5.5" and "1e3" are too ambiguous to guess at
			if (1 === preg_match('/^-?\d+$/', $normalised)) {
				return (int) $normalised;
			}
		}

		throw ConfigurationExceptionFactory::notAByteCount($option, $value);
	}
}
