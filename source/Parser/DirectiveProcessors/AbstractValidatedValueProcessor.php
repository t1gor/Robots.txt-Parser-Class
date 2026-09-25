<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

/**
 * A directive whose value is validated and then kept as the tree's own string - one per
 * user-agent, or a list of them where the spec allows the directive to repeat.
 */
abstract class AbstractValidatedValueProcessor extends AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	use DeduplicatesEntriesTrait;

	/** The canonical form to store, or null when the value cannot be read as this directive. */
	abstract protected function normalise(string $value): ?string;

	/** Several per user-agent, e.g. a Request-rate per window. Someone else's directive is not in the enum. */
	protected function isRepeatable(): bool {
		return $this->repeatable ??= (Directive::tryFrom($this->getDirectiveName())?->isRepeatable() ?? false);
	}

	private ?bool $repeatable = null;

	public function process(string $line, array &$root, string &$currentUserAgent = '*', string $prevLine = ''): void {
		$directive = $this->getDirectiveName();
		$entry     = $this->normalise($this->value($line));

		if (is_null($entry)) {
			$this->log(strtr('{directive} with value "{faulty}" dropped as invalid for {useragent}', [
				'{directive}' => $directive,
				'{faulty}'    => $this->value($line),
				'{useragent}' => $currentUserAgent,
			]));

			return;
		}

		if (!$this->isRepeatable()) {
			$root[$currentUserAgent][$directive] = $entry;

			return;
		}

		$root[$currentUserAgent][$directive] ??= [];

		if ($this->isDuplicate($root[$currentUserAgent][$directive], $currentUserAgent, $entry)) {
			$this->log(strtr('{directive} with value "{faulty}" skipped as already exists for {useragent}', [
				'{directive}' => $directive,
				'{faulty}'    => $entry,
				'{useragent}' => $currentUserAgent,
			]));

			return;
		}

		$root[$currentUserAgent][$directive][] = $entry;
	}
}
