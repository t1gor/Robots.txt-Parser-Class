<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Config;

use t1gor\RobotsTxtParser\Configuration;

/**
 * The names a {@see Configuration} setting goes by outside PHP - the array key a framework config
 * layer hands over, and the environment variable or constant behind {@see ConfigurationFactory::ENV_PREFIX}.
 */
enum Option: string {

	case BYTE_LIMIT = 'byte_limit';
	case DEFAULT_ENCODING = 'default_encoding';
	case PARSE_ENCODING = 'parse_encoding';
	case WRITE_ENCODING = 'write_encoding';

	/** @return self[] the charset settings, in the order {@see Configuration} takes them */
	public static function encodings(): array {
		return [self::DEFAULT_ENCODING, self::PARSE_ENCODING, self::WRITE_ENCODING];
	}

	public function envName(): string {
		return ConfigurationFactory::ENV_PREFIX . strtoupper($this->value);
	}

	/** @return string[] every option name, for the message that lists them */
	public static function names(): array {
		return array_column(self::cases(), 'value');
	}
}
