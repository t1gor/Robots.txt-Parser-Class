<?php
	class WhitespacesTest extends \PHPUnit_Framework_TestCase
	{
		/**
		 * @dataProvider generateDataForTest
		 * @covers RobotsTxtParser::isDisallowed
		 * @covers RobotsTxtParser::checkRules
		 * @param string $robotsTxtContent
		 */
		public function testWhitespaces($robotsTxtContent)
		{
			// init parser
			$parser = new RobotsTxtParser($robotsTxtContent);
			$this->assertInstanceOf('RobotsTxtParser', $parser);

			$this->assertNotEmpty($parser->getRules('*'), 'expected rules for *');
			$this->assertArrayHasKey('disallow', $parser->getRules('*'));
			$this->assertNotEmpty($parser->getRules('*')['disallow'], 'disallow failed');
			$this->assertArrayHasKey('allow', $parser->getRules('*'));
			$this->assertNotEmpty($parser->getRules('*')['allow'], 'allow failed');
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
