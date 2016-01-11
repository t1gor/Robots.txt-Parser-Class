<?php

class HostTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider generateDataForTest
	 * @covers       RobotsTxtParser::getHost
	 * @param string $robotsTxtContent
	 */
	public function testHost($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$this->assertEquals('myhost.ru', $parser->getHost());
	}

	/**
	 * Generate test case data
	 * @return array
	 */
	public function generateDataForTest()
	{
		return array(
			array(<<<ROBOTS
Host: myhost.ru

User-agent: *
Disallow: /cgi-bin

User-agent: Yandex
Disallow: /cgi-bin
Host: www.myhost.ru # is not used
ROBOTS
			)
		);
	}
}
