<?php
	/**
	 * @backupGlobals disabled
	 */
	class HostTest extends \PHPUnit_Framework_TestCase
	{
		/**
		 * Load library
		 */
		public static function setUpBeforeClass()
		{
			require_once(realpath(__DIR__.'/../robotstxtparser.php'));
		}

		/**
		 * @dataProvider generateDataForTest
		 * @covers RobotsTxtParser::isDisallowed
		 * @covers RobotsTxtParser::checkRule
		 * @param string $robotsTxtContent
		 */
		public function testHost($robotsTxtContent)
		{
			// init parser
			$parser = new RobotsTxtParser($robotsTxtContent);
			$this->assertInstanceOf('RobotsTxtParser', $parser);
			$this->assertObjectHasAttribute('rules', $parser);
			$this->assertArrayHasKey('*', $parser->rules);
			$this->assertArrayHasKey('host', $parser->rules['*']);
			$this->assertEquals(array('www.aquaphor.ru'), $parser->rules['*']['host']);
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
					Host: www.aquaphor.ru
				")
			);
		}
	}
