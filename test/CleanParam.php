<?php
	/**
	 * @backupGlobals disabled
	 */
	class CleanParamTest extends \PHPUnit_Framework_TestCase
	{
		/**
		 * Load library
		 */
		public static function setUpBeforeClass()
		{
			require_once(realpath(__DIR__.'/../robotstxtparser.php'));
		}

		/**
		 * @link https://help.yandex.ru/webmaster/controlling-robot/robots-txt.xml#clean-param
		 *
		 * @dataProvider generateDataForTest
		 * @covers RobotsTxtParser::isDisallowed
		 * @covers RobotsTxtParser::checkRule
		 * @param string $robotsTxtContent
		 */
		public function testCleanParam($robotsTxtContent, $message = NULL)
		{
			// init parser
			$parser = new RobotsTxtParser($robotsTxtContent);
			$rules = $parser->getRules();
			$this->assertInstanceOf('RobotsTxtParser', $parser);
			$this->assertObjectHasAttribute('rules', $parser);
			$this->assertArrayHasKey('*', $rules);
			$this->assertArrayHasKey('clean-param', $rules['*']);
			$this->assertEquals(array('utm_source&utm_medium&utm.campaign'), $rules['*']['clean-param'], $message);
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
