<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * @note those paths are invalid and there is no point for checking those.
 *       This would only overcomplicate the code and has no potential use case.
 *
 * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/69
 */
class InvalidPathTest extends TestCase {

	public function testInvalidPathAllowed() {
		$parser = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/disallow-all.txt', 'r'));
		$this->assertTrue($parser->isAllowed('*wildcard'));
		$this->assertFalse($parser->isDisallowed("&&1@|"));
		$this->assertTrue($parser->isAllowed('+£€@@1¤'));
	}
}
