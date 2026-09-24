<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class RobotVersionProcessor extends AbstractValidatedValueProcessor implements DirectiveProcessorInterface {

	/** The version of the spec the record is written to, e.g. "2.0". */
	private const VERSION = '/^\d+(\.\d+)*$/';

	public function getDirectiveName(): string {
		return Directive::ROBOT_VERSION->value;
	}

	protected function normalise(string $value): ?string {
		return 1 === preg_match(self::VERSION, $value) ? $value : null;
	}
}
