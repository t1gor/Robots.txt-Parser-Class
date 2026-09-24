<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AbstractDirectiveProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\DisallowProcessor;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * matches() interpolates the directive name straight into the pattern.
 *
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AbstractDirectiveProcessor::matches
 */
class DirectiveNameMatchingTest extends TestCase {

	private function processorNamed(string $name): AbstractDirectiveProcessor {
		return new class($name) extends AbstractDirectiveProcessor {
			private string $name;

			public function __construct($name = null) {
				parent::__construct(null);
				$this->name = (string) $name;
			}

			public function getDirectiveName(): string {
				return $this->name;
			}

			public function process(string $line, array &$root, string &$currentUserAgent = '*', string $prevLine = ''): void {}
		};
	}

	public function testDirectiveNameWithRegexMetacharsIsEscaped() {
		$processor = $this->processorNamed('a.b');

		$this->assertTrue($processor->matches('a.b: /x'));
		$this->assertFalse($processor->matches('axb: /x'));
	}

	public function testDirectiveNameIsNotMatchedAsPrefixOfAnother() {
		$processor = new DisallowProcessor();

		$this->assertTrue($processor->matches('disallow: /x'));
		$this->assertFalse($processor->matches('allow: /x'));
	}

	public function testMatchingIsCaseInsensitive() {
		$processor = new DisallowProcessor();

		$this->assertTrue($processor->matches('DISALLOW: /x'));
		$this->assertTrue($processor->matches('DisAllow: /x'));
	}

	/**
	 * @dataProvider spacingVariants
	 */
	public function testSpacingAroundColon(string $line) {
		$this->assertTrue((new DisallowProcessor())->matches($line));
	}

	public function spacingVariants(): array {
		return [
			['disallow:/x'],
			['disallow: /x'],
			['disallow :/x'],
			['disallow  :  /x'],
			["disallow\t:\t/x"],
		];
	}

	/** Kept by the unsupported-directives filter, but no processor handles them. */
	public function testDirectivesWithoutProcessorAreIgnored() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nRequest-rate: 1/5\nVisit-time: 0600-0845\nDisallow: /a\n");

		$this->assertSame(['*' => ['disallow' => ['/a']]], $parser->getRules());
	}

	/** matches() anchors at ^, so it depends on the trim filter having run. */
	public function testIndentedDirective() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\n\t\tDisallow: /admin\n");

		$this->assertTrue($parser->isDisallowed('/admin'));
	}
}
