<?php

/**
 * @group empty
 */
class EmptyRulesShouldAllowEverythingTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/23
	 */
	public function testEmptyRulesAllow()
	{
		$parser = new RobotsTxtParser('');
		$this->assertTrue($parser->isAllowed('/foo'));
		$this->assertFalse($parser->isDisallowed('/foo'));
	}
}
