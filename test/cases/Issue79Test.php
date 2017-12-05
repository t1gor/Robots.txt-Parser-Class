<?php

use PHPUnit\Framework\TestCase;

/**
 * @group github-issues
 */
class Issue79Test extends TestCase
{
    public function testParse()
    {
        $content = file_get_contents(FIXTURE_PATH . '/issue-79.txt');
        $parser = new RobotsTxtParser($content);
        $this->assertNotEmpty($parser->getRules());
    }
}
