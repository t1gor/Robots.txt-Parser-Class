<?php
	/**
	 * @backupGlobals disabled
	 */
	class WithWhitespacesTest extends \PHPUnit_Framework_TestCase
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
		public function testWithWhitespaces($robotsTxtContent)
		{
			// init parser
			$parser = new RobotsTxtParser($robotsTxtContent);
			$this->assertInstanceOf('RobotsTxtParser', $parser);

			$this->assertNotEmpty($parser->getRules('*'), 'expected rules for *');
			$this->assertFalse($parser->isDisallowed("/admin"), 'failed disallowed');
			$this->assertTrue($parser->isAllowed("/admin/front"), 'failed allow');
		}

		/**
		 * Generate test case data
		 * @return array
		 */
		public function generateDataForTest()
		{
			return array(
				array("
					User-agent: *
					Disallow : /admin
					Allow    :   /admin/front
				")
			);
		}
	}
