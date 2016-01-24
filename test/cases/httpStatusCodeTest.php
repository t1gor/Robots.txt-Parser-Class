<?php

class httpStatusCodeTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @dataProvider generateDataForTest
	 * @param string $robotsTxtContent
	 */
	public function testHttpStatusCode($robotsTxtContent)
	{
		// init parser
		$parser = new RobotsTxtParser($robotsTxtContent);
		$this->assertInstanceOf('RobotsTxtParser', $parser);
		$parser->setHttpStatusCode(200);
		$this->assertTrue($parser->isAllowed("/"));
		$this->assertFalse($parser->isDisallowed("/"));
		$this->assertContains('Rule match: Path', $parser->getLog());
		$parser->setHttpStatusCode(503);
		$this->assertTrue($parser->isDisallowed("/"));
		$this->assertFalse($parser->isAllowed("/"));
		$this->assertContains('Disallowed by HTTP status code 5xx', $parser->getLog());
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
					Allow: /
				")
		);
	}
}
