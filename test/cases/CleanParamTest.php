<?php

class CleanParamTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @link https://help.yandex.ru/webmaster/controlling-robot/robots-txt.xml#clean-param
	 *
	 * @dataProvider generateDataForTest
	 * @covers       RobotsTxtParser::isDisallowed
	 * @covers       RobotsTxtParser::checkRules
	 * @param string $robotsTxtContent
	 */
	public function testCleanParam($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$cleanParam = $parser->getCleanParam();
		$this->assertInstanceOf('RobotsTxtParser', $parser);

		$this->assertTrue($parser->isDisallowed("http://www.site1.com/forums/showthread.php?s=681498b9648949605&ref=parent"));
		$this->assertFalse($parser->isAllowed("http://www.site1.com/forums/showthread.php?s=681498b9648949605&ref=parent"));

		$this->assertTrue($parser->isAllowed("http://www.site2.com/forums/showthread.php?s=681498b9648949605"));
		$this->assertFalse($parser->isDisallowed("http://www.site2.com/forums/showthread.php?s=681498b9648949605"));

		$this->assertArrayHasKey('abc', $cleanParam);
		$this->assertEquals(array('/forum/showthread.php'), $cleanParam['abc']);

		$this->assertArrayHasKey('sid', $cleanParam);
		$this->assertEquals(array('/forumt/*.php'), $cleanParam['sid']);

		$this->assertArrayHasKey('sort', $cleanParam);
		$this->assertEquals(array('/forumt/*.php'), $cleanParam['sort']);

		$this->assertArrayHasKey('someTrash', $cleanParam);
		$this->assertEquals(array('/*'), $cleanParam['someTrash']);

		$this->assertArrayHasKey('otherTrash', $cleanParam);
		$this->assertEquals(array('/*'), $cleanParam['otherTrash']);
	}

	/**
	 * Generate test case data
	 * @return array
	 */
	public function generateDataForTest()
	{
		return array(
			array(<<<ROBOTS
User-agent: *
Disallow: Clean-param: s&ref /forum*/sh*wthread.php
Clean-param: abc /forum/showthread.php
Clean-param: sid&sort /forumt/*.php
Clean-param: someTrash&otherTrash
ROBOTS
			)
		);
	}
}
