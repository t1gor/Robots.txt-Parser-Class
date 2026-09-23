<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream;

use Psr\Log\LoggerAwareInterface;

interface ReaderInterface extends LoggerAwareInterface {
	public function setEncoding(string $encoding);
	public function getContentIterated(): \Iterator;
	public function getContentRaw(): string;

	/** Whether the input ran past the configured byte limit and was cut short. */
	public function wasTruncated(): bool;

	/** @return string[] */
	public function filters(): array;
}
