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
	private array $filters;

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
					'class'   => $class,
					'message' => $throwable->getMessage(),
				]);
			}
		}

		if (is_resource($this->stream)) {
			fclose($this->stream);
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

	/**
	 * @param string $encoding
	 *
	 * @TODO check on composer install if we have filters available
	 */
	public function setEncoding(string $encoding) {
		if (strtoupper($encoding) === RobotsTxtParser::DEFAULT_ENCODING) {
			return;
		}

		$this->log(WarmingMessages::ENCODING_NOT_UTF8, [], LogLevel::WARNING);

		$filterName = 'convert.iconv.' . $encoding . '/utf-8';
		$this->log('Adding encoding filter ' . $filterName);

		// convert encoding
		$this->filters['iconv'] = stream_filter_prepend($this->stream, $filterName, STREAM_FILTER_READ);
	}

	public function getContentIterated(): \Generator {
		rewind($this->stream);

		while (!feof($this->stream)) {
			$line = fgets($this->stream);

			if (false !== $line) {
				yield $line;
			}
		}
	}

	public function getContentRaw(): string {
		rewind($this->stream);
		return stream_get_contents($this->stream);
	}
}
