<?php
	class CommentsTest extends \PHPUnit_Framework_TestCase
	{
		/**
		 * @dataProvider generateDataForTest
		 * @covers RobotsTxtParser::isDisallowed
		 * @covers RobotsTxtParser::checkRules
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
		 * @covers RobotsTxtParser::checkRules
		 * @param string $robotsTxtContent
		 * @param string $expectedDisallowValue
		 */
		public function testRemoveCommentsFromValue($robotsTxtContent, $expectedDisallowValue)
		{
			$parser = new RobotsTxtParser($robotsTxtContent);
			$this->assertInstanceOf('RobotsTxtParser', $parser);
			$this->assertNotEmpty($parser->getRules('*'), 'expected data');
			$this->assertArrayHasKey('disallow', $parser->getRules('*'));
			$this->assertNotEmpty($parser->getRules('*')['disallow'], 'disallow expected');
			$this->assertEquals($expectedDisallowValue, $parser->getRules('*')['disallow'][0]);
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
