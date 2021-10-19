<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream\Filters;

use Psr\Log\LoggerInterface;
use t1gor\RobotsTxtParser\Stream\CustomFilterInterface;

class SkipCommentedLinesFilter extends \php_user_filter implements CustomFilterInterface {

	public const NAME = 'RTP_skip_commented_lines';

	public $filtername = self::NAME;

	public function filter($in, $out, &$consumed, $closing) {
		while ($bucket = stream_bucket_make_writeable($in)) {
			$replacedCount = 0;
			$bucket->data = preg_replace('/^#.*/mui', '', $bucket->data, -1, $replacedCount);
			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);

			if ($replacedCount > 0
				&& isset($this->params['logger'])
				&& $this->params['logger'] instanceof LoggerInterface
			) {
				$this->params['logger']->debug($replacedCount . ' lines skipped as commented out');
			}
		}

		return PSFS_PASS_ON;
	}
}
