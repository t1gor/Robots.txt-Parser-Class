<?php

class HostTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider generateDataForTest
	 * @covers       RobotsTxtParser::getHost
	 * @covers       RobotsTxtParser::checkRules
	 * @param string $robotsTxtContent
	 */
	public function testHost($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$this->assertTrue($parser->isDisallowed("http://www.myhost.ru/"));
		$this->assertFalse($parser->isAllowed("http://www.myhost.ru/"));
		$this->assertTrue($parser->isAllowed("http://myhost.ru/"));
		$this->assertFalse($parser->isDisallowed("http://myhost.ru/"));
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
User-agent: *
Disallow: /cgi-bin
Disallow: Host: www.myhost.ru

User-agent: Yandex
Disallow: /cgi-bin

# Examples of Host directives that will be ignored
Host: www.myhost-.com
Host: www.-myhost.com
Host: www.myhost.com:100000
Host: www.my_host.com
Host: .my-host.com:8000
Host: my-host.com.Host: my..host.com
Host: www.myhost.com:8080/
Host: 213.180.194.129
Host: [2001:db8::1]
Host: FE80::0202:B3FF:FE1E:8329
Host: https://[2001:db8:0:1]:80
Host: www.firsthost.ru,www.secondhost.com
Host: www.firsthost.ru www.secondhost.com

# Examples of valid Host directives
Host: myhost.ru # uses this one
Host: www.myhost.ru # is not used
ROBOTS
			)
		);
	}
}
