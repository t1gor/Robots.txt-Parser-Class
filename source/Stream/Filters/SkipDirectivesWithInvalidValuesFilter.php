<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream\Filters;

use Psr\Log\LoggerInterface;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Stream\CustomFilterInterface;

/**
 * @TODO add checks for more directives
 */
class SkipDirectivesWithInvalidValuesFilter extends \php_user_filter implements CustomFilterInterface {

	use KeepsDataOnInvalidUtf8Trait;

	public const NAME = 'RTP_skip_directives_invalid_value';

	public function filter($in, $out, &$consumed, $closing): int {
		while ($bucket = stream_bucket_make_writeable($in)) {
			$skippedRequestRateValues = 0;
			$skippedCrawlDelayValues = 0;
			$skippedAllowanceValues = 0;

			$bucket->data = self::replaceOrKeep(Directive::getRequestRateRegex(), '', $bucket->data, $skippedRequestRateValues);
			$bucket->data = self::replaceOrKeep(Directive::getCrawlDelayRegex(), '', $bucket->data, $skippedCrawlDelayValues);
			$bucket->data = self::replaceOrKeep(Directive::getAllowDisallowRegex(), '', $bucket->data, $skippedAllowanceValues);

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
