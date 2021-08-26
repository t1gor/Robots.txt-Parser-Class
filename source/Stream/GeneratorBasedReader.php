<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream;

use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\Stream\Filters\SkipDirectivesWithInvalidValuesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipEndOfCommentedLineFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipCommentedLinesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipEmptyLinesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipUnsupportedDirectivesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\TrimSpacesLeftFilter;
use t1gor\RobotsTxtParser\WarmingMessages;

class GeneratorBasedReader implements ReaderInterface {

	use LogsIfAvailableTrait;

	private $stream;

	/**
	 * @var string[]
	 */
	private array  $filters;
	private string $encoding;

	protected function __construct() {
		/** @note order matters */
		$this->filters = [
			SkipCommentedLinesFilter::class              => false,
			SkipEndOfCommentedLineFilter::class          => false,
			TrimSpacesLeftFilter::class                  => false,
			SkipUnsupportedDirectivesFilter::class       => false,
			SkipDirectivesWithInvalidValuesFilter::class => false,
			SkipEmptyLinesFilter::class                  => false,
		];
	}

	/**
	 * @link https://www.php.net/manual/en/function.stream-filter-append.php#84637
	 */
	public function __destruct() {
		foreach ($this->filters as $class => $instance) {
			try {
				if (is_resource($instance)) {
					stream_filter_remove($instance);
				}
			} catch (\Throwable $throwable) {
				$this->log('Failed to remove filter "{class}": {message}', [
					'class' => $class,
					'message' => $throwable->getMessage()
				]);
			}
		}
	}

	/**
	 * @param string $input
	 *
	 * @return static
	 */
	public static function fromString(string $input = ''): self {
		$reader = new GeneratorBasedReader();
		$stream = tmpfile();

		fwrite($stream, $input);
		fseek($stream, 0);

		$reader->log(WarmingMessages::STRING_INIT_DEPRECATE);

		return $reader->setStream($stream);
	}

	public static function fromStream($stream): self {
		if (!is_resource($stream)) {
			$error = sprintf('Argument must be a valid resource type. %s given.', gettype($stream));
			throw new \InvalidArgumentException($error);
		}

		$reader = new GeneratorBasedReader();
		rewind($stream);

		return $reader->setStream($stream);
	}

	protected function setStream($stream): GeneratorBasedReader {
		$this->stream = $stream;

		foreach ($this->filters as $filterClass => & $filter) {
			stream_filter_register($filterClass::NAME, $filterClass);
			$filter = stream_filter_append(
				$this->stream,
				$filterClass::NAME,
				STREAM_FILTER_READ,
				['logger' => $this->logger] // pass logger to filters
			);
		}

		return $this;
	}

	public function setEncoding(string $encoding) {
		$this->encoding = $encoding;

		if (strtoupper($encoding) !== RobotsTxtParser::DEFAULT_ENCODING) {
			$this->log(WarmingMessages::ENCODING_NOT_UTF8, [], LogLevel::WARNING);
		}
	}

	public function getContent(): \Generator {
		rewind($this->stream);

		while (!feof($this->stream)) {
			$line = fgets($this->stream);

			if (false !== $line) {
				yield $line;
			}
		}
	}
}
