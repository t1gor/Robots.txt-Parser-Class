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
	public function testCleanParam($robotsTxtContent, $message = NULL)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$this->assertEquals(array('utm_source&utm_medium&utm.campaign'), $parser->getCleanParam(), $message);
	}

	/**
	 * Generate test case data
	 * @return array
	 */
	public function generateDataForTest()
	{
		return array(
			array(
				"
					User-Agent: *
					#Clean-param: utm_source_commented&comment
					Clean-param: utm_source&utm_medium&utm.campaign
					",
				'with comment'
			),
			array(
				"
					User-Agent: *
					Clean-param: utm_source&utm_medium&utm.campaign
					Clean-param: utm_source&utm_medium&utm.campaign
					",
				'expected to remove repetitions of lines'
			),
		);
	}
}
