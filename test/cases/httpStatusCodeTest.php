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
		$parser->setHttpStatusCode(200);
		$this->assertTrue($parser->isAllowed("/"));
		$this->assertFalse($parser->isDisallowed("/"));
		$parser->setHttpStatusCode(503);
		$this->assertTrue($parser->isDisallowed("/"));
		$this->assertFalse($parser->isAllowed("/"));
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
