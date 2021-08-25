<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream\Filters;

use t1gor\RobotsTxtParser\Stream\CustomFilterInterface;

class SkipEndOfCommentedLineFilter extends \php_user_filter implements CustomFilterInterface {

	public const NAME = 'RTP_skip_end_of_commented_line';

	public $filtername = self::NAME;

	public function filter($in, $out, &$consumed, $closing) {
		while ($bucket = stream_bucket_make_writeable($in)) {
			$bucket->data = preg_replace('/\s#.*/mui', '', $bucket->data);
			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);
		}

		return PSFS_PASS_ON;
	}
}
