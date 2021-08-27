<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\DirectiveProcessorInterface;

class TreeBuilder implements TreeBuilderInterface {

	use LogsIfAvailableTrait;

	/**
	 * @var DirectiveProcessorInterface[]
	 */
	protected array     $processors;
	protected \Iterator $content;

	/**
	 * @param DirectiveProcessorInterface[] $processors
	 * @param LoggerInterface|null          $logger
	 */
	public function __construct(array $processors, ?LoggerInterface $logger) {
		$this->logger = $logger;

		if (empty($processors)) {
			$this->log("Seems like you've passed an empty processors array.", [], LogLevel::WARNING);
		}

		// reformat processors
		foreach ($processors as $processor) {
			$this->processors[$processor->getDirectiveName()] = $processor;
		}
	}

	/**
	 * Wrapper to check that processor is available
	 */
	protected function processDirective(string $directive, string $line, &$tree, string &$userAgent, string $prevLine = '') {
		if (!isset($this->processors[$directive])) {
			$this->log(strtr('{directive} met, but no processor found for it. Skipping.', [
				'{directive}' => $directive,
			]));
			return;
		}

		$this->processors[$directive]->process($line, $tree, $userAgent, $prevLine);
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

	/**
	 * @return array
	 * @todo check for multibyte support?
	 */
	public function build(): array {
		$currentUserAgent = '*';
		$tree             = [];
		$prevLine         = '';

		$this->log('Building directives tree...');

		foreach ($this->content as $line) {
			foreach ($this->processors as $processor) {
				if ($processor->matches($line)) {
					$this->processDirective(
						$processor->getDirectiveName(),
						$line,
						$tree,
						$currentUserAgent,
						$prevLine
					);
					break;
				}
			}

			// override
			$prevLine = $line;
		}

		return $tree;
	}
}
