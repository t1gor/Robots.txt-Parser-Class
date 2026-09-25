<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use t1gor\RobotsTxtParser\Directive;

/** A note to whoever runs the crawler - free text, kept verbatim. */
class CommentProcessor extends AbstractValidatedValueProcessor implements DirectiveProcessorInterface {

	/** Anything that would not survive being written back on one line. */
	private const UNSAFE = '/[\x00-\x1F\x7F#]/';

	public function getDirectiveName(): string {
		return Directive::COMMENT->value;
	}

	protected function normalise(string $value): ?string {
		return '' === $value || 1 === preg_match(self::UNSAFE, $value) ? null : $value;
	}
}
