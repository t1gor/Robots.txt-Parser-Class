<?php

class EncodingTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @param string $encoding
     */
    public function testEncoding($encoding)
    {
        /**
         * Test currently disabled due to issues
         * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/70
         */
        // Invalid encodings are ignored, and the default encoding is used, without warning.
        $parser = new RobotsTxtParser('', $encoding);
        $this->assertInstanceOf('RobotsTxtParser', $parser);
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
                'UTF9' //invalid
            ],
            [
                'ASCI' //invalid
            ],
            [
                'ISO8859' //invalid
            ],
            [
                'OSF10020402' //iconv
            ],
            [
                'UTF-16' //mbstring/iconv
            ],
        ];
    }
}
