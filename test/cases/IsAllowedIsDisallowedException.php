<?php

/**
 * Note: Test-data may become outdated, and the test will most likely fail when issue #22 is addressed.
 * @link https://github.com/t1gor/Robots.txt-Parser-Class/issues/22
 */
class IsAllowedIsDisallowedException extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider generateDataForTest
	 * @covers       RobotsTxtParser::isDisallowed
	 * @expectedException \DomainException
	 * @expectedExceptionMessage Unable to check rules
	 * @param string $robotsTxtContent
	 */
	public function testIsAllowedException($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$parser->isAllowed("/");
	}

	/**
	 * @dataProvider generateDataForTest
	 * @covers       RobotsTxtParser::isDisallowed
	 * @expectedException \DomainException
	 * @expectedExceptionMessage Unable to check rules
	 * @param string $robotsTxtContent
	 */
	public function testIsDisallowedException($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$parser->isDisallowed("/");
	}

	/**
	 * Generate test case data
	 *
	 * @return array
	 */
	public function generateDataForTest()
	{
		return array(
			array("
					User-Agent: *
					Disallow: /admin/
				")
		);
	}
}
