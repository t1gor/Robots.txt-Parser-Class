<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class CleanParamProcessor extends AbstractDirectiveProcessor implements DirectiveProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::CLEAN_PARAM->value;
	}

	public function process(string $line, array & $root, string & $currentUserAgent = '*', string $prevLine = ''): void {
		$parts                               = explode(':', $line);
		$cleanParams                         = explode(' ', trim($parts[1]));
		$path                                = $cleanParams[1] ?? '/*';
		$root[Directive::CLEAN_PARAM->value][$path] = explode('&', $cleanParams[0]);
	}
}
