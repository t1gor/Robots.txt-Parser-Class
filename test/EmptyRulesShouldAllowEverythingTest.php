<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @group empty
 */
class EmptyRulesShouldAllowEverythingTest extends TestCase
{
	/**
	 * @cover RobotsTxtParser::checkRule
	 * @cover RobotsTxtParser::getHost
	 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/23
	 */
	public function testEmptyRulesAllow()
	{
		$parser = new RobotsTxtParser('');
		$this->assertTrue($parser->isAllowed('/foo'));
		$this->assertFalse($parser->isDisallowed('/foo'));
		$this->assertNull($parser->getHost());
	}

	/**
	 * A declared agent whose only rule is commented out - the agent stays in the tree,
	 * but with nothing to match against.
	 */
	public function testAgentWithOnlyCommentedRulesAllows()
	{
		$parser = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/with-empty-rules.txt', 'r'));

		$this->assertSame(['*' => []], $parser->getRules());
		$this->assertTrue($parser->isAllowed('/tech'));
		$this->assertFalse($parser->isDisallowed('/tech'));
		$this->assertNull($parser->getHost());
	}
}
