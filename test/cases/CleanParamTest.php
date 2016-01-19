<?php

class CleanParamTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @link https://help.yandex.ru/webmaster/controlling-robot/robots-txt.xml#clean-param
	 *
	 * @dataProvider generateDataForTest
	 * @covers       RobotsTxtParser::isDisallowed
	 * @covers       RobotsTxtParser::getCleanParam
	 * @param string $robotsTxtContent
	 */
	public function testCleanParam($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$cleanParam = $parser->getCleanParam();

		$this->assertTrue($parser->isDisallowed("http://www.site1.com/forums/showthread.php?s=681498b9648949605&ref=parent"));
		$this->assertFalse($parser->isAllowed("http://www.site1.com/forums/showthread.php?s=681498b9648949605&ref=parent"));

		$this->assertTrue($parser->isAllowed("http://www.site2.com/forums/showthread.php?s=681498b9648949605"));
		$this->assertFalse($parser->isDisallowed("http://www.site2.com/forums/showthread.php?s=681498b9648949605"));

		$this->assertArrayHasKey('/forum/showthread.php', $cleanParam);
		$this->assertEquals(array('abc'), $cleanParam['/forum/showthread.php']);

		$this->assertArrayHasKey('/forum/*.php', $cleanParam);
		$this->assertEquals(array('sid', 'sort'), $cleanParam['/forum/*.php']);

		$this->assertArrayHasKey('/*', $cleanParam);
		$this->assertEquals(array('someTrash', 'otherTrash'), $cleanParam['/*']);
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
Clean-param: sid&sort /forum/*.php
Clean-param: someTrash&otherTrash
ROBOTS
			)
		);
	}
}
