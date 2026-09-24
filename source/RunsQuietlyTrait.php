<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser;

/**
 * Runs something with PHP's diagnostics captured rather than emitted: `@` only silences handlers
 * that respect error_reporting(), and a strict one would turn a warning into an exception.
 */
trait RunsQuietlyTrait {

	/** @return array{0: mixed, 1: string[]} the return value, and every message raised */
	protected function quietly(callable $action): array {
		$raised = [];

		set_error_handler(function (int $number, string $message) use (&$raised): bool {
			$raised[] = $message;

			return true;
		});

		try {
			return [$action(), $raised];
		} finally {
			restore_error_handler();
		}
	}
}
