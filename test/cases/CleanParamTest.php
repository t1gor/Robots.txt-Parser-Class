<?php

class CleanParamTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @link https://help.yandex.ru/webmaster/controlling-robot/robots-txt.xml#clean-param
	 *
	 * @dataProvider generateDataForTest
	 * @covers       RobotsTxtParser::isDisallowed
	 * @covers       RobotsTxtParser::checkRule
	 * @param string $robotsTxtContent
	 */
	public function testCleanParam($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$cleanParam = $parser->getCleanParam();

		$this->assertArrayHasKey('abc', $cleanParam);
		$this->assertEquals($cleanParam['abc'], '/forum/showthread.php');

		$this->assertArrayHasKey('sid', $cleanParam);
		$this->assertEquals($cleanParam['sid'], '/forumt/*.php');

		$this->assertArrayHasKey('sort', $cleanParam);
		$this->assertEquals($cleanParam['sort'], '/forumt/*.php');

		$this->assertArrayHasKey('someTrash', $cleanParam);
		$this->assertEquals($cleanParam['someTrash'], '/*');

		$this->assertArrayHasKey('otherTrash', $cleanParam);
		$this->assertEquals($cleanParam['otherTrash'], '/*');
	}

	/**
	 * Generate test case data
	 * @return array
	 */
	public function generateDataForTest()
	{
		return array(
			array(<<<ROBOTS
Clean-param: abc /forum/showthread.php
Clean-param: sid&sort /forumt/*.php
Clean-param: someTrash&otherTrash
ROBOTS
			)
		);
	}
}
