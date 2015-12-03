<?php
class CacheDelayTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @param string $robotsTxtContent
     */
    public function testCacheDelay($robotsTxtContent)
    {
        // init parser
        $parser = new RobotsTxtParser($robotsTxtContent);
        $this->assertEquals(3.7, $parser->getCacheDelay('*'));
        $this->assertEquals(8, $parser->getCacheDelay('AhrefsBot'));
    }

    /**
     * Generate test case data
     * @return array
     */
    public function generateDataForTest()
    {
        return array(
            array("
					User-Agent: *
					Cache-Delay: 3.7
					User-Agent: AhrefsBot
					Cache-Delay: 8
				")
        );
    }
}
