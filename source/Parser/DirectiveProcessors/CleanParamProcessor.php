<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

class CleanParamProcessor extends AbstractInvokableProcessor implements InvokableProcessorInterface {

	public function getDirectiveName(): string {
		return Directive::CLEAN_PARAM;
	}

	public function __invoke(string $line, array & $root, string & $currentUserAgent = '*') {
		$parts                               = explode(':', $line);
		$cleanParams                         = explode(' ', trim($parts[1]));
		$path                                = $cleanParams[1] ?? '/*';
		$root[Directive::CLEAN_PARAM][$path] = explode('&', $cleanParams[0]);
	}
}
