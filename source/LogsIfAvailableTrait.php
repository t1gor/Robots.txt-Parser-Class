<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Logger\BufferedLogger;

trait LogsIfAvailableTrait {

	private ?LoggerInterface $logger = null;

	/** Always somewhere to log: until a real logger arrives, messages buffer rather than vanish. */
	protected function logger(): LoggerInterface {
		return $this->logger ??= new BufferedLogger();
	}

	public function setLogger(LoggerInterface $logger): void {
		$buffered = $this->logger;

		$this->logger = $logger;

		if ($buffered instanceof BufferedLogger) {
			$buffered->attach($logger);
		}

		$this->onLoggerSet($logger);
	}

	/** For classes that hand the logger on to things they own. */
	protected function onLoggerSet(LoggerInterface $logger): void {
	}

	protected function log(string $message, array $context = [], string $level = LogLevel::DEBUG): void {
		$this->logger()->log($level, $message, $context);
	}
}
