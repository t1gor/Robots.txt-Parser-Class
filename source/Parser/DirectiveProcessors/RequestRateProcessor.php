<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\RequestRate;

class RequestRateProcessor extends AbstractValidatedValueProcessor implements DirectiveProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::REQUEST_RATE->value;
	}

	protected function normalise(string $value): ?string {
		return RequestRate::tryParse($value)?->__toString();
	}
}
