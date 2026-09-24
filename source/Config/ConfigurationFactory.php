<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Config;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\Exception\ByteCountOutOfRangeException;
use t1gor\RobotsTxtParser\Exception\ConfigurationExceptionFactory;
use t1gor\RobotsTxtParser\Exception\InvalidByteCountException;
use t1gor\RobotsTxtParser\Exception\UnknownOptionException;

/** Builds and checks a {@see Configuration} from whatever shape the host application uses. */
final class ConfigurationFactory {

	/** Shared with the stream filter names. */
	const ENV_PREFIX = 'RTP_';

	/** @var string[] */
	const KNOWN_OPTIONS = [Configuration::OPTION_BYTE_LIMIT];

	/** How config files and env vars spell "read it all". */
	private const UNLIMITED_ALIASES = ['none', 'unlimited'];

	/**
	 * Every framework config layer ends up as an array, so this is the portable entry point.
	 * Missing keys keep their defaults.
	 *
	 * @param array{byte_limit?: int|numeric-string|null} $options
	 *
	 * @throws UnknownOptionException|InvalidByteCountException|ByteCountOutOfRangeException
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

		return self::build(self::toByteCount(
			Configuration::OPTION_BYTE_LIMIT,
			$options[Configuration::OPTION_BYTE_LIMIT]
		));
	}

	/**
	 * Falls back to a constant of the same name: WordPress defines those instead of env vars.
	 *
	 * @param array<string, mixed>|null $env Injectable so tests never touch the real environment.
	 *
	 * @throws InvalidByteCountException|ByteCountOutOfRangeException
	 */
	public static function fromEnvironment(?array $env = null): Configuration {
		$name = self::ENV_PREFIX . 'BYTE_LIMIT';
		$raw  = self::readEnv($name, $env);

		// unset or blank is not a decision - keep the default
		return is_null($raw) || '' === trim($raw)
			? new Configuration()
			: self::build(self::toByteCount($name, $raw));
	}

	/**
	 * Configuration holds no rules of its own, so anything handed one built directly runs it past
	 * this first. Unusable settings throw, legal but questionable ones are logged.
	 *
	 * @throws ByteCountOutOfRangeException
	 */
	public static function validate(Configuration $config, ?LoggerInterface $logger = null): void {
		// 0 is what people reach for when they mean null, and reading nothing is never meaningful
		if (($config->byteLimit ?? 1) <= 0) {
			throw ConfigurationExceptionFactory::notPositive(
				Configuration::OPTION_BYTE_LIMIT,
				$config->byteLimit
			);
		}

		if (is_null($logger)) {
			return;
		}

		foreach (self::warningsFor($config) as $warning) {
			$logger->log(LogLevel::WARNING, $warning['message'], $warning['context']);
		}
	}

	/** @return array<int, array{message: string, context: array<string, mixed>}> */
	private static function warningsFor(Configuration $config): array {
		if (is_null($config->byteLimit)) {
			return [[
				'message' => 'Byte limit is disabled: the whole robots.txt will be read however large '
					. 'it is, risking memory and CPU exhaustion on a hostile or runaway file.',
				'context' => [Configuration::OPTION_BYTE_LIMIT => null],
			]];
		}

		if ($config->byteLimit < Configuration::RECOMMENDED_MIN_BYTE_LIMIT) {
			return [[
				'message' => 'Byte limit {byte_limit} is below the recommended minimum {recommended}: '
					. 'robots.txt may be truncated to nothing, and unmatched paths default to allowed.',
				'context' => [
					Configuration::OPTION_BYTE_LIMIT => $config->byteLimit,
					'recommended'                    => Configuration::RECOMMENDED_MIN_BYTE_LIMIT,
				],
			]];
		}

		return [];
	}

	/** @throws ByteCountOutOfRangeException */
	private static function build(?int $byteLimit): Configuration {
		$config = new Configuration($byteLimit);

		self::validate($config);

		return $config;
	}

	private static function readEnv(string $name, ?array $env): ?string {
		$value = match (true) {
			!is_null($env)                       => $env[$name] ?? null,
			false !== ($fromEnv = getenv($name)) => $fromEnv,
			defined($name)                       => constant($name),
			default                              => null,
		};

		return is_scalar($value) ? (string) $value : null;
	}

	/** Config layers hand over strings as often as ints. @throws InvalidByteCountException */
	private static function toByteCount(string $option, mixed $value): ?int {
		// null for anything non-string, so the string arms below simply never match
		$normalised = is_string($value) ? strtolower(trim($value)) : null;

		return match (true) {
			is_null($value) => null,
			// ahead of any is_scalar check, or a bool silently becomes 1
			is_int($value)  => $value,
			in_array($normalised, self::UNLIMITED_ALIASES, true) => null,
			// whole numbers only: "5.5" and "1e3" are too ambiguous to guess at
			1 === preg_match('/^-?\d+$/', (string) $normalised)  => (int) $normalised,
			default => throw ConfigurationExceptionFactory::notAByteCount($option, $value),
		};
	}
}
