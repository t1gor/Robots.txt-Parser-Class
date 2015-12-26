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
        $this->assertEquals(0, $parser->getDelay('*', 'cache-delay'));
        $this->assertEquals(3.7, $parser->getDelay('GoogleBot', 'cache-delay'));
        $this->assertEquals(8, $parser->getDelay('AhrefsBot', 'cache-delay'));
    }

    /**
     * Generate test case data
     * @return array
     */
    public function generateDataForTest()
    {
        return array(
            array("
					User-Agent: GoogleBot
					Cache-Delay: 3.7
					User-Agent: AhrefsBot
					Cache-Delay: 8
				")
        );
    }
}
