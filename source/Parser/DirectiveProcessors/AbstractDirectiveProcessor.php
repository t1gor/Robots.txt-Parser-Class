<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use Psr\Log\LoggerInterface;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;

abstract class AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	use LogsIfAvailableTrait;

	public function __construct(?LoggerInterface $logger = null) {
		$this->logger = $logger;
	}

	public function getLogger(): ?LoggerInterface {
		return $this->logger;
	}

	public function matches(string $line): bool {
		return (bool) preg_match('/^' . $this->getDirectiveName() . '\s*:\s+/isu', $line);
	}
}
