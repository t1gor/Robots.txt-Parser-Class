<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class AllowProcessor extends AbstractAllowanceProcessor implements InvokableProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::ALLOW;
	}
}
