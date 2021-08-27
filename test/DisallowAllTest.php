<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @group disallow-all
 * @link  https://github.com/t1gor/Robots.txt-Parser-Class/issues/22
 */
class DisallowAllTest extends TestCase {

	public function testDisallowWildcard() {
		$parser = new RobotsTxtParser(file_get_contents(__DIR__ . '/Fixtures/disallow-all.txt'));
		$this->assertTrue($parser->isDisallowed("/index"));
		$this->assertFalse($parser->isAllowed("/index"));
	}

	public function testAllowWildcard() {
		$parser = new RobotsTxtParser(file_get_contents(__DIR__ . '/Fixtures/allow-all.txt'));
		$this->assertFalse($parser->isDisallowed("/index"));
		$this->assertFalse($parser->isDisallowed("/"));
		$this->assertTrue($parser->isAllowed("/index"));
		$this->assertTrue($parser->isAllowed("/"));
	}
}
