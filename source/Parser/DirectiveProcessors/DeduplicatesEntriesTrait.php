<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

/**
 * Spotting a repeated value by scanning everything kept so far made one big group cost O(n^2).
 * Past a few hundred entries this keeps an index instead; below that the scan is both quicker
 * and smaller than the index would be, so the memory of a normal file is untouched.
 */
trait DeduplicatesEntriesTrait {

	/** Measured: at this size the scan and the index cost about the same, and the index also costs memory. */
	private const INDEX_FROM = 256;

	/** @var array<string, array<string, mixed>> user-agent => entries already kept, big groups only */
	private array $seen = [];

	/**
	 * True when the value is already kept for this user-agent; otherwise it is recorded as kept.
	 *
	 * The index is rebuilt whenever it disagrees with the tree's length, which is what keeps it
	 * honest when the tree arrives pre-filled, is reused for the next document, or is shared by
	 * two user-agents that {@see UserAgentProcessor} linked together.
	 */
	protected function isDuplicate(array $existing, string $userAgent, string $entry): bool {
		if (count($existing) < self::INDEX_FROM) {
			return in_array($entry, $existing, true);
		}

		if (count($this->seen[$userAgent] ?? []) !== count($existing)) {
			$this->seen[$userAgent] = array_flip($existing);
		}

		$duplicate                      = isset($this->seen[$userAgent][$entry]);
		$this->seen[$userAgent][$entry] = true;

		return $duplicate;
	}
}
