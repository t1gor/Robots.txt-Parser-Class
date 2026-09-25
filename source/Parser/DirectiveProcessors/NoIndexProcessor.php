<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

/** Same shape as Disallow - a path - but about the index rather than the crawl. */
class NoIndexProcessor extends AbstractAllowanceProcessor implements DirectiveProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::NOINDEX->value;
	}
}
