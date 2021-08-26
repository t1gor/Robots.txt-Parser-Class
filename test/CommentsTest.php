<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class CommentsTest extends TestCase
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
			$this->assertNotEmpty($parser->getRules('*'), 'expected data');
			$this->assertArrayHasKey(Directive::DISALLOW, $parser->getRules('*'));
			$this->assertNotEmpty($parser->getRules('*')[Directive::DISALLOW], 'disallow expected');
			$this->assertEquals($expectedDisallowValue, $parser->getRules('*')[Directive::DISALLOW][0]);
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
