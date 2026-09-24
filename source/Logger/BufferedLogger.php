<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Holds messages until a real logger turns up, then hands them over and forwards from there on.
 *
 * Forwarding, not just replaying: stream filters are handed a logger when they are applied and
 * never see a later one.
 */
final class BufferedLogger extends AbstractLogger {

	/** Capped so a logger nobody attaches cannot grow without end. */
	public const MAX_RECORDS = 500;

	private ?LoggerInterface $target = null;

	/** @var array<int, array{level: mixed, message: mixed, context: array<string, mixed>}> */
	private array $records = [];

	public function log($level, $message, array $context = []): void {
		if (!is_null($this->target)) {
			$this->target->log($level, $message, $context);

			return;
		}

		$this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];

		// the tail of a parse explains more than its opening
		$this->records = array_slice($this->records, -self::MAX_RECORDS);
	}

	public function attach(LoggerInterface $target): void {
		// attaching to ourselves would loop forever
		if ($target === $this) {
			return;
		}

		$this->target = $target;

		foreach ($this->records as $record) {
			$target->log($record['level'], $record['message'], $record['context']);
		}

		$this->records = [];
	}

	/** @return array<int, array{level: mixed, message: mixed, context: array<string, mixed>}> */
	public function getRecords(): array {
		return $this->records;
	}
}
