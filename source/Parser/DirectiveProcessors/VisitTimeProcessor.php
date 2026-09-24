<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\TimeWindow;

class VisitTimeProcessor extends AbstractValidatedValueProcessor implements DirectiveProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::VISIT_TIME->value;
	}

	protected function normalise(string $value): ?string {
		return TimeWindow::tryParse($value)?->__toString();
	}
}
