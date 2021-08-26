<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser;

use Psr\Log\LoggerInterface;

trait LogsIfAvailableTrait {

	private ?LoggerInterface $logger = null;

	public function setLogger(LoggerInterface $logger): void {
		$this->logger = $logger;
	}

	protected function log(string $message, array $context = []) {
		if (!is_null($this->logger)) {
			$this->logger->debug($message, $context);
		}
	}
}
