<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream\Filters;

use t1gor\RobotsTxtParser\Stream\CustomFilterInterface;

class EnsureEndOfLinesFilter extends \php_user_filter implements CustomFilterInterface
{
    public const NAME = 'RTP_ensure_end_of_lines';

    /** Everything downstream splits on LF, so never PHP_EOL - that is CRLF on Windows. */
    private const EOL = "\n";

    protected string $incompleteLine = '';

    public function filter($in, $out, &$consumed, $closing): int
    {
        $buffer = $this->incompleteLine;
        while ($bucket = stream_bucket_make_writeable($in)) {
            $buffer .= $bucket->data;
        }
        $consumed += mb_strlen($buffer);

        $this->incompleteLine = '';

        // A CR at the very end may be the first half of a CRLF split across
        // chunks, so hold it back until the next pass shows what follows.
        $heldCr = '';
        if (!$closing && str_ends_with($buffer, "\r")) {
            $buffer = substr($buffer, 0, -1);
            $heldCr = "\r";
        }

        // CR, LF and CRLF all terminate a line (RFC 9309), fgets() only knows LF
        $buffer = preg_replace("/\r\n?/", self::EOL, $buffer);

        if (!$closing) {
            // Есть как минимум один перенос строки
            if (preg_match("/((?s)^.*\n)(.*)$/mui", $buffer, $matches)) {
                $buffer               = $matches[1];              // все строки с EOL
                $this->incompleteLine = $matches[2] . $heldCr;    // последняя строка без EOL
            } else {
                // Всего одна строка без EOL, ждём следующей порции
                $this->incompleteLine = $buffer . $heldCr;
                return \PSFS_FEED_ME;
            }
        }
        $bucket = stream_bucket_new($this->stream, $buffer);
        stream_bucket_append($out, $bucket);
        return \PSFS_PASS_ON;
    }
}
