<?php

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
}
