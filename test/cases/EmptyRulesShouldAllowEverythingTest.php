<?php

/**
 * @group empty
 */
class EmptyRulesShouldAllowEverythingTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @cover RobotsTxtParser::checkRule
	 * @cover RobotsTxtParser::getHost
	 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/23
	 */
	public function testEmptyRulesAllow()
	{
		$parser = new RobotsTxtParser('');
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$this->assertTrue($parser->isAllowed('/foo'));
		$this->assertFalse($parser->isDisallowed('/foo'));
		$this->assertNull($parser->getHost());
	}
}
