<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Config;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\Exception\ByteCountOutOfRangeException;
use t1gor\RobotsTxtParser\Exception\ConfigurationExceptionFactory;
use t1gor\RobotsTxtParser\Exception\InvalidByteCountException;
use t1gor\RobotsTxtParser\Exception\InvalidEncodingException;
use t1gor\RobotsTxtParser\Exception\UnknownOptionException;

/** Builds and checks a {@see Configuration} from whatever shape the host application uses. */
final class ConfigurationFactory {

	/** Shared with the stream filter names. */
	const ENV_PREFIX = 'RTP_';

	/** How config files and env vars spell "read it all". */
	private const UNLIMITED_ALIASES = ['none', 'unlimited'];

	/**
	 * Every framework config layer ends up as an array, so this is the portable entry point.
	 * Missing keys keep their defaults.
	 *
	 * @param array{byte_limit?: int|numeric-string|null, default_encoding?: string|null, parse_encoding?: string|null, write_encoding?: string|null} $options
	 * @param ?LoggerInterface $logger where the legal-but-questionable settings are reported
	 *
	 * @throws UnknownOptionException|InvalidByteCountException|ByteCountOutOfRangeException|InvalidEncodingException
	 */
	public static function fromArray(array $options, ?LoggerInterface $logger = null): Configuration {
		$given = [];

		foreach ($options as $key => $value) {
			$option = Option::tryFrom((string) $key);

			if (is_null($option)) {
				throw ConfigurationExceptionFactory::unknownOption((string) $key, Option::names());
			}

			$given[$option->value] = $value;
		}

		return self::build($given, $logger);
	}

	/**
	 * Falls back to a constant of the same name: WordPress defines those instead of env vars.
	 *
	 * @param array<string, mixed>|null $env    Injectable so tests never touch the real environment.
	 * @param ?LoggerInterface           $logger where the legal-but-questionable settings are reported
	 *
	 * @throws InvalidByteCountException|ByteCountOutOfRangeException|InvalidEncodingException
	 */
	public static function fromEnvironment(?array $env = null, ?LoggerInterface $logger = null): Configuration {
		$given = [];

		foreach (Option::cases() as $option) {
			$raw = self::readEnv($option->envName(), $env);

			// unset or blank is not a decision - keep the default
			if (is_null($raw) || (is_string($raw) && '' === trim($raw))) {
				continue;
			}

			$given[$option->value] = $raw;
		}

		return self::build($given, $logger);
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
			throw ConfigurationExceptionFactory::notPositive(Option::BYTE_LIMIT, $config->byteLimit);
		}

		if (is_null($logger)) {
			return;
		}

		foreach (self::warningsFor($config) as $warning) {
			$logger->log(LogLevel::WARNING, $warning['message'], $warning['context']);
		}
	}

	/**
	 * @param array<string, mixed> $given only the options that were actually set
	 *
	 * @throws ByteCountOutOfRangeException|InvalidByteCountException|InvalidEncodingException
	 */
	private static function build(array $given, ?LoggerInterface $logger = null): Configuration {
		$encodings = [];

		foreach (Option::encodings() as $option) {
			$encodings[$option->value] = self::toEncoding($option, $given[$option->value] ?? null);
		}

		$config = new Configuration(
			array_key_exists(Option::BYTE_LIMIT->value, $given)
				? self::toByteCount(Option::BYTE_LIMIT, $given[Option::BYTE_LIMIT->value])
				: Configuration::DEFAULT_BYTE_LIMIT,
			$encodings[Option::DEFAULT_ENCODING->value] ?? Configuration::DEFAULT_ENCODING,
			$encodings[Option::PARSE_ENCODING->value],
			$encodings[Option::WRITE_ENCODING->value]
		);

		self::validate($config, $logger);

		return $config;
	}

	/** @return array<int, array{message: string, context: array<string, mixed>}> */
	private static function warningsFor(Configuration $config): array {
		return array_merge(self::byteLimitWarnings($config), self::encodingWarnings($config));
	}

	/** @return array<int, array{message: string, context: array<string, mixed>}> */
	private static function byteLimitWarnings(Configuration $config): array {
		if (is_null($config->byteLimit)) {
			return [[
				'message' => 'Byte limit is disabled: the whole robots.txt will be read however large '
					. 'it is, risking memory and CPU exhaustion on a hostile or runaway file.',
				'context' => [Option::BYTE_LIMIT->value => null],
			]];
		}

		if ($config->byteLimit < Configuration::RECOMMENDED_MIN_BYTE_LIMIT) {
			return [[
				'message' => 'Byte limit {byte_limit} is below the recommended minimum {recommended}: '
					. 'robots.txt may be truncated to nothing, and unmatched paths default to allowed.',
				'context' => [
					Option::BYTE_LIMIT->value => $config->byteLimit,
					'recommended'             => Configuration::RECOMMENDED_MIN_BYTE_LIMIT,
				],
			]];
		}

		return [];
	}

	/**
	 * Warned about rather than thrown: reading falls back to the bytes as they are, and a caller
	 * that only parses should not be stopped by a write setting it never reaches.
	 *
	 * @return array<int, array{message: string, context: array<string, mixed>}>
	 */
	private static function encodingWarnings(Configuration $config): array {
		$byEncoding = [];

		// grouped, so one unusable default is reported once naming both the options it reached
		foreach ([
			Option::PARSE_ENCODING->value => $config->encodingForParsing(),
			Option::WRITE_ENCODING->value => $config->encodingForWriting(),
		] as $option => $encoding) {
			$byEncoding[$encoding][] = $option;
		}

		$warnings = [];

		foreach ($byEncoding as $encoding => $options) {
			if (self::isKnownCharset((string) $encoding)) {
				continue;
			}

			$warnings[] = [
				'message' => 'Encoding {encoding} is not a charset iconv knows: the document is read '
					. 'as-is, and writing it out throws rather than serving the wrong bytes.',
				'context' => ['encoding' => $encoding, 'options' => $options],
			];
		}

		return $warnings;
	}

	/**
	 * iconv answers false for a charset it does not know whatever the input, so an empty string
	 * settles it. Handler swapped rather than `@` - see {@see \t1gor\RobotsTxtParser\RunsQuietlyTrait}.
	 */
	private static function isKnownCharset(string $encoding): bool {
		set_error_handler(static fn (): bool => true);

		try {
			return false !== iconv($encoding, 'UTF-8', '');
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Left as the scalar it was, not cast: a real environment variable is always a string, but a
	 * WordPress constant is whatever wp-config.php defined, and the same rules should judge it.
	 */
	private static function readEnv(string $name, ?array $env): mixed {
		$value = match (true) {
			!is_null($env)                       => $env[$name] ?? null,
			false !== ($fromEnv = getenv($name)) => $fromEnv,
			defined($name)                       => constant($name),
			default                              => null,
		};

		return is_scalar($value) ? $value : null;
	}

	/** Config layers hand over strings as often as ints. @throws InvalidByteCountException */
	private static function toByteCount(Option $option, mixed $value): ?int {
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

	/** @throws InvalidEncodingException */
	private static function toEncoding(Option $option, mixed $value): ?string {
		if (is_null($value)) {
			return null;
		}

		if (!is_string($value)) {
			throw ConfigurationExceptionFactory::notAnEncoding($option, $value);
		}

		// blank is not a decision either - let the fallback answer instead
		return '' === ($trimmed = trim($value)) ? null : $trimmed;
	}
}
