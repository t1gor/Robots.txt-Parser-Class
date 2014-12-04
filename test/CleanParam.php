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
		public function testCleanParam($robotsTxtContent)
		{
			// init parser
			$parser = new RobotsTxtParser($robotsTxtContent);
			$this->assertInstanceOf('RobotsTxtParser', $parser);
			$this->assertObjectHasAttribute('rules', $parser);
			$this->assertArrayHasKey('*', $parser->rules);
			$this->assertArrayHasKey('clean-param', $parser->rules['*']);
			$this->assertEquals(array('utm_source&utm_medium&utm.campaign'), $parser->rules['*']['clean-param']);
		}

		/**
		 * Generate test case data
		 * @return array
		 */
		public function generateDataForTest()
		{
			return array(
				array("
					User-Agent: *
					#Clean-param: utm_source_commented&comment
					Clean-param: utm_source&utm_medium&utm.campaign
				"),
			);
		}
	}
