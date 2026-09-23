<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream;

use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\Stream\Filters\EnsureEndOfLinesFilter;
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

	/** @var array<class-string<CustomFilterInterface>, resource|false> Applied in order. */
	private array $filters;

	/** @var resource|false|null Separate from $filters - not one of our classes. */
	private $encodingFilter = null;

	private ?string $encodingFilterName = null;

	protected function __construct() {
		/** @note order matters */
		$this->filters = [
			EnsureEndOfLinesFilter::class                => false,
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
		$applied = $this->filters;

		if (is_resource($this->encodingFilter)) {
			$applied[$this->encodingFilterName] = $this->encodingFilter;
		}

		foreach ($applied as $class => $instance) {
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
			$name = $filterClass::NAME;

			// Registration is process-wide, so later readers re-use it.
			if (in_array($name, stream_get_filters(), true)) {
				$this->log('Filter {name} already registered, re-using it', ['name' => $name]);
			} elseif (!stream_filter_register($name, $filterClass)) {
				$this->log('Failed to register filter {name}, input will not be filtered by it', [
					'name' => $name,
				], LogLevel::WARNING);
				continue;
			}

			$filter = stream_filter_append(
				$this->stream,
				$name,
				STREAM_FILTER_READ,
				['logger' => $this->logger] // pass logger to filters
			);

			if (false === $filter) {
				$this->log('Failed to apply filter {name}, input will not be filtered by it', [
					'name' => $name,
				], LogLevel::WARNING);
			}
		}

		unset($filter);

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
		$this->encodingFilterName = $filterName;
		$this->encodingFilter     = stream_filter_prepend($this->stream, $filterName, STREAM_FILTER_READ);

		if (false === $this->encodingFilter) {
			$this->log('Failed to apply encoding filter {name}, content is read as-is', [
				'name' => $filterName,
			], LogLevel::WARNING);
		}
	}

	/**
	 * Applied filters, in the order they run.
	 *
	 * @return string[]
	 */
	public function filters(): array {
		$applied = [];

		foreach ($this->filters as $filterClass => $filter) {
			if (is_resource($filter)) {
				$applied[] = $filterClass::NAME;
			}
		}

		// Prepended, so it runs first.
		if (is_resource($this->encodingFilter)) {
			array_unshift($applied, $this->encodingFilterName);
		}

		return $applied;
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
