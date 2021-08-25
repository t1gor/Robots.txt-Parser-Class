<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream\Filters;

use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Stream\CustomFilterInterface;

/**
 * @TODO add checks for more directives
 */
class SkipDirectivesWithInvalidValuesFilter extends \php_user_filter implements CustomFilterInterface {

	public const NAME = 'RTP_skip_directives_invalid_value';

	public $filtername = self::NAME;

	public function filter($in, $out, &$consumed, $closing) {
		while ($bucket = stream_bucket_make_writeable($in)) {
			$bucket->data = preg_replace(Directive::getRequestRateRegex(), '', $bucket->data);
			$bucket->data = preg_replace(Directive::getCrawlDelayRegex(), '', $bucket->data);
			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);
		}

		return PSFS_PASS_ON;
	}
}
