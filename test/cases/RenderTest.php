<?php

/**
 * @group render
 */
class RenderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @param string $robotsTxtContent
     * @param string|false $rendered
     */
    public function testRender($robotsTxtContent, $rendered)
    {
        $parser = new RobotsTxtParser($robotsTxtContent);
        $this->assertInstanceOf('RobotsTxtParser', $parser);
        $this->assertEquals($rendered, $parser->render("\n"));
    }

    /**
     * Generate test data
     *
     * @return array
     */
    public function generateDataForTest()
    {
        return [
            [
                file_get_contents(FIXTURE_PATH . '/render-source.txt'),
                file_get_contents(FIXTURE_PATH . '/render-result.txt')
            ]
        ];
    }
}
