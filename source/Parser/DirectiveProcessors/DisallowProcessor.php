<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class DisallowProcessor extends AbstractAllowanceProcessor implements DirectiveProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::DISALLOW;
	}
}
