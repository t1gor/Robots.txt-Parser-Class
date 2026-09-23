<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream\Filters;

use t1gor\RobotsTxtParser\Stream\CustomFilterInterface;

class EnsureEndOfLinesFilter extends \php_user_filter implements CustomFilterInterface
{
    public const NAME = 'RTP_ensure_end_of_lines';

    public $filtername = self::NAME;

    protected string $incompleteLine = '';

    public function filter($in, $out, &$consumed, $closing)
    {
        $buffer = $this->incompleteLine;
        while ($bucket = stream_bucket_make_writeable($in)) {
            $buffer .= $bucket->data;
        }
        $consumed += mb_strlen($buffer);

        $this->incompleteLine = '';
        if (!$closing) {
            // Есть как минимум один перенос строки
            if (preg_match("/((?s)^.*[\n\r])(.*)$/mui", $buffer, $matches)) {
                $buffer = $matches[1];                  // все строки с EOL
                $this->incompleteLine = $matches[2];    // последняя строка без EOL
            } else {
                // Всего одна строка без EOL, ждём следующей порции
                $this->incompleteLine = $buffer;
                return \PSFS_FEED_ME;
            }
        }
        $bucket = stream_bucket_new($this->stream, $buffer);
        stream_bucket_append($out, $bucket);
        return \PSFS_PASS_ON;
    }
}
