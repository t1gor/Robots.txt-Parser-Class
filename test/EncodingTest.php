<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\RobotsTxtParser;

class EncodingTest extends TestCase
{
    /**
     * @dataProvider generateDataForTest
     * @param string $encoding
     */
    public function testEncoding($encoding)
    {
    	$this->markTestSkipped('@TODO');

        /**
         * Test currently disabled due to issues
         * @see https://github.com/t1gor/Robots.txt-Parser-Class/issues/70
         */
        // Invalid encodings are ignored, and the default encoding is used, without warning.
        $parser = new RobotsTxtParser('', $encoding);
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
                'UTF9' // invalid
            ],
            [
                'ASCI' // invalid
            ],
            [
                'ISO8859' // invalid
            ],
            [
                'OSF10020402' // iconv
            ],
            [
                'UTF-16' // mbstring / iconv
            ],
        ];
    }

	public function testWindows1251Readable() {
		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$parser = new RobotsTxtParser(fopen(__DIR__ . '/Fixtures/market-yandex-Windows-1251.txt', 'r'), 'Windows-1251');
		$parser->setLogger($log);

		$allRules = $parser->getRules();

		$this->assertCount(5, $allRules, json_encode(array_keys($allRules)));
    }
}
