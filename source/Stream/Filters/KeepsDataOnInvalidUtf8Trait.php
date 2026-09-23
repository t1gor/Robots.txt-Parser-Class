<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream\Filters;

trait KeepsDataOnInvalidUtf8Trait {

	/**
	 * A /u pattern returns null on invalid UTF-8. Keeping the original bytes leaves the
	 * rules readable instead of blanking the bucket - and feeding null to the next filter.
	 */
	protected static function replaceOrKeep(string $pattern, string $replacement, string $subject, ?int &$count = null): string {
		$replaced = preg_replace($pattern, $replacement, $subject, -1, $count);

		if (null === $replaced) {
			$count = 0;

			return $subject;
		}

		return $replaced;
	}
}
