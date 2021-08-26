<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream;

use Psr\Log\LoggerAwareInterface;

interface ReaderInterface extends LoggerAwareInterface {
	public function setEncoding(string $encoding);
	public function getContent(): \Iterator;
}
