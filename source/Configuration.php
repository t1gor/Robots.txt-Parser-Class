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

	/** What the spec asks for, and what the rules tree is held in whatever the document was. */
	const DEFAULT_ENCODING = 'UTF-8';

	/**
	 * Reading and writing are separate settings because they are separate decisions: a document
	 * served as Windows-1251 can be republished as UTF-8.
	 *
	 * @param int|null    $byteLimit       Bytes to read at most, null for no limit.
	 * @param string      $defaultEncoding Answers for whichever of the two below is unset.
	 * @param string|null $parseEncoding   What the document being read is in.
	 * @param string|null $writeEncoding   What to write out as.
	 */
	public function __construct(
		public ?int $byteLimit = self::DEFAULT_BYTE_LIMIT,
		public string $defaultEncoding = self::DEFAULT_ENCODING,
		public ?string $parseEncoding = null,
		public ?string $writeEncoding = null
	) {
	}

	public function encodingForParsing(): string {
		return $this->parseEncoding ?? $this->defaultEncoding;
	}

	public function encodingForWriting(): string {
		return $this->writeEncoding ?? $this->defaultEncoding;
	}
}
