<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessorsFactory;

/**
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessorsFactory::getDefault
 */
class ProcessorCoverageTest extends TestCase {

	/** A directive the filters keep but nothing handles would be read and then silently dropped. */
	public function testEveryDirectiveTheFiltersKeepHasAProcessor() {
		$handled = array_map(
			fn ($processor): string => $processor->getDirectiveName(),
			DirectiveProcessorsFactory::getDefault()
		);

		sort($handled);
		$expected = Directive::getAll();
		sort($expected);

		$this->assertSame($expected, $handled);
	}

	/** Lines are offered to each in turn, so the common directives have to come first. */
	public function testTheCommonDirectivesAreTriedFirst() {
		$order = array_map(
			fn ($processor): string => $processor->getDirectiveName(),
			DirectiveProcessorsFactory::getDefault()
		);

		$this->assertLessThan(array_search(Directive::NOINDEX->value, $order), array_search(Directive::DISALLOW->value, $order));
		$this->assertLessThan(array_search(Directive::COMMENT->value, $order), array_search(Directive::ALLOW->value, $order));
	}
}
