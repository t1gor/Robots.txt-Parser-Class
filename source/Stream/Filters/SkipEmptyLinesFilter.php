<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream\Filters;

use Psr\Log\LoggerInterface;
use t1gor\RobotsTxtParser\Stream\CustomFilterInterface;

class SkipEmptyLinesFilter extends \php_user_filter implements CustomFilterInterface {

	use KeepsDataOnInvalidUtf8Trait;

	public const NAME = 'RTP_skip_empty_lines';

	public function filter($in, $out, &$consumed, $closing): int {
		while ($bucket = stream_bucket_make_writeable($in)) {
			$replacedCount = 0;
			$bucket->data = self::replaceOrKeep(
				'/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/mui',
				PHP_EOL,
				$bucket->data,
				$replacedCount
			);

			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);

			if ($replacedCount > 0
				&& isset($this->params['logger'])
				&& $this->params['logger'] instanceof LoggerInterface
			) {
				$this->params['logger']->debug($replacedCount . ' lines skipped as empty.');
			}
		}

		return PSFS_PASS_ON;
	}
}
