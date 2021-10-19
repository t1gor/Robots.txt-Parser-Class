<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream\Filters;

use Psr\Log\LoggerInterface;
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
			$skippedRequestRateValues = 0;
			$skippedCrawlDelayValues = 0;
			$skippedAllowanceValues = 0;

			$bucket->data = preg_replace(Directive::getRequestRateRegex(), '', $bucket->data, -1, $skippedRequestRateValues);
			$bucket->data = preg_replace(Directive::getCrawlDelayRegex(), '', $bucket->data, -1, $skippedCrawlDelayValues);
//			$bucket->data = preg_replace(Directive::getAllowDisallowRegex(), '', $bucket->data, -1, $skippedAllowanceValues);

			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);

			if (isset($this->params['logger']) && $this->params['logger'] instanceof LoggerInterface) {
				if ($skippedRequestRateValues > 0) {
					$this->params['logger']->debug($skippedRequestRateValues . ' char(s) dropped as invalid Request-rate value.');
				}
				if ($skippedCrawlDelayValues > 0) {
					$this->params['logger']->debug($skippedCrawlDelayValues . ' char(s) dropped as invalid Crawl-delay value.');
				}
				if ($skippedAllowanceValues > 0) {
					$this->params['logger']->debug($skippedAllowanceValues . ' char(s) dropped as invalid allow/disallow value.');
				}
			}
		}

		return PSFS_PASS_ON;
	}
}
