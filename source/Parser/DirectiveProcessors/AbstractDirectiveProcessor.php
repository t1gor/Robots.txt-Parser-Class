<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use Psr\Log\LoggerInterface;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;

abstract class AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	use LogsIfAvailableTrait;

	public function __construct(?LoggerInterface $logger = null) {
		$this->logger = $logger;
	}

	public function getLogger(): ?LoggerInterface {
		return $this->logger;
	}

	/** The directive name never changes, so neither does the pattern built from it. */
	private ?string $pattern = null;

	/** Everything past the first colon: a value may hold colons of its own. */
	protected function value(string $line): string {
		$colon = strpos($line, ':');

		return false === $colon ? '' : trim(substr($line, $colon + 1));
	}

	public function matches(string $line): bool {
		// Whitespace around the colon is optional: "disallow:/" is as valid as "disallow: /".
		// Built once per processor: every line is offered to every processor until one matches.
		$this->pattern ??= '/^' . preg_quote($this->getDirectiveName(), '/') . '\s*:\s*/isu';

		return (bool) preg_match($this->pattern, $line);
	}
}
