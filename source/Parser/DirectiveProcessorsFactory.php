<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

use Psr\Log\LoggerInterface;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AllowProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CacheDelayProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CleanParamProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CrawlDelayProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\DisallowProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\HostProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\SitemapProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\UserAgentProcessor;

abstract class DirectiveProcessorsFactory {

	public static function getDefault(?LoggerInterface $logger = null): array {
		return [
			new UserAgentProcessor($logger),
			new CrawlDelayProcessor($logger),
			new CacheDelayProcessor($logger),
			new HostProcessor($logger),
			new CleanParamProcessor($logger),
			new SitemapProcessor($logger),
			new AllowProcessor($logger),
			new DisallowProcessor($logger),
		];
	}
}
