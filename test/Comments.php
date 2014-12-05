<?php
	/**
	 * @backupGlobals disabled
	 */
	class CommentsTest extends \PHPUnit_Framework_TestCase
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
		public function testRemoveComments($robotsTxtContent)
		{
			$parser = new RobotsTxtParser($robotsTxtContent);
			$this->assertInstanceOf('RobotsTxtParser', $parser);

			$rules = $parser->getRules('*');

			$this->assertEmpty($rules, 'expected remove comments');
		}

		/**
		 * @dataProvider generateDataFor2Test
		 * @covers RobotsTxtParser::isDisallowed
		 * @covers RobotsTxtParser::checkRule
		 * @param string $robotsTxtContent
		 */
		public function testRemoveCommentsFromValue($robotsTxtContent, $expectedDisallowValue)
		{
			$parser = new RobotsTxtParser($robotsTxtContent);
			$this->assertInstanceOf('RobotsTxtParser', $parser);

			$rules = $parser->getRules('*');

			$this->assertNotEmpty($rules, 'expected data');
			$this->assertArrayHasKey('disallow', $rules);
			$this->assertNotEmpty($rules['disallow'], 'disallow expected');
			$this->assertEquals($expectedDisallowValue, $rules['disallow'][0]);
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
					#Disallow: /tech
				"),
				array("
					User-agent: *
					Disallow: #/tech
				"),
				array("
					User-agent: *
					Disal # low: /tech
				"),
				array("
					User-agent: *
					Disallow#: /tech # ds
				"),
			);
		}

		/**
		 * Generate test case data
		 * @return array
		 */
		public function generateDataFor2Test()
		{
			return array(
				array(
					"User-agent: *
						Disallow: /tech #comment",
					'disallowValue' => '/tech',
				),
			);
		}
	}
