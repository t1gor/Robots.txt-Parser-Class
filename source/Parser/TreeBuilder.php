<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

use Psr\Log\LoggerInterface;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\InvokableProcessorInterface;

class TreeBuilder implements TreeBuilderInterface {

	use LogsIfAvailableTrait;

	protected array     $processors;
	protected \Iterator $content;

	/**
	 * @param InvokableProcessorInterface[] $processors
	 * @param LoggerInterface|null          $logger
	 */
	public function __construct(array $processors, ?LoggerInterface $logger) {
		$this->logger = $logger;

		if (empty($processors)) {
			$this->log("Seems like you've passed an empty processors array.");
		}

		// reformat processors
		foreach ($processors as $processor) {
			$this->processors[$processor->getDirectiveName()] = $processor;
		}
	}

	/**
	 * Wrapper to check that processor is available
	 */
	protected function processDirective(string $directive, string $line, &$tree, string &$userAgent) {
		if (!isset($this->processors[$directive])) {
			$this->log('{directive} met, but no processor found for it. Skipping.', [
				'{directive}' => $directive,
			]);
			return;
		}

		$this->processors[$directive]($line, $tree, $userAgent);
	}

	/**
	 * @return \Iterator
	 */
	public function getContent(): \Iterator {
		return $this->content;
	}

	/**
	 * @param \Iterator $content
	 */
	public function setContent(\Iterator $content): void {
		$this->content = $content;
	}

	public function build(): array {
		$currentUserAgent = '*';
		$tree             = [];

		$this->log('Building directives tree...');

		foreach ($this->content as $line) {
			switch (true) {
				case preg_match('/^' . Directive::USERAGENT . '\s*:\s+/isu', $line):
					$this->processDirective(Directive::USERAGENT, $line, $tree, $currentUserAgent);
					break;

				case preg_match('/^' . Directive::CRAWL_DELAY . '\s*:\s+/isu', $line):
					$this->processDirective(Directive::CRAWL_DELAY, $line, $tree, $currentUserAgent);
					break;

				case preg_match('/^' . Directive::CACHE_DELAY . '\s*:\s+/isu', $line):
					$this->processDirective(Directive::CACHE_DELAY, $line, $tree, $currentUserAgent);
					break;

				case preg_match('/^' . Directive::ALLOW . '\s*:\s+/isu', $line):
					$this->processDirective(Directive::ALLOW, $line, $tree, $currentUserAgent);
					break;

				case preg_match('/^' . Directive::DISALLOW . '\s*:\s+/isu', $line):
					$this->processDirective(Directive::DISALLOW, $line, $tree, $currentUserAgent);
					break;

				case preg_match('/^' . Directive::HOST . '\s*:\s+/isu', $line):
					$this->processDirective(Directive::HOST, $line, $tree, $currentUserAgent);
					break;

				case preg_match('/^' . Directive::SITEMAP . '\s*:\s+/isu', $line):
					$this->processDirective(Directive::SITEMAP, $line, $tree, $currentUserAgent);
					break;

				case preg_match('/^' . Directive::CLEAN_PARAM . '\s*:\s+/isu', $line):
					$this->processDirective(Directive::CLEAN_PARAM, $line, $tree, $currentUserAgent);
					break;
			}
		}

		return $tree;
	}
}
