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
					// a filter left mid-conversion warns instead of throwing, so capture either
					[, $raised] = $this->quietly(function () use ($instance) {
						return stream_filter_remove($instance);
					});

					if ([] !== $raised) {
						$this->log('Filter "{class}" did not shut down cleanly: {errors}', [
							'class'  => $class,
							'errors' => implode('; ', $raised),
						]);
					}
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
	 * An encoding iconv doesn't know is ignored, not fatal - the content is then read as-is.
	 *
	 * @param string $encoding
	 */
	public function setEncoding(string $encoding) {
		$encoding = trim($encoding);

		// "utf8", "UTF-8", "utf_8" - all the same thing, and nothing to convert
		if (in_array(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $encoding)), ['', 'UTF8'], true)) {
			return;
		}

		$filterName = 'convert.iconv.' . $encoding . '/utf-8';

		// buildTree() re-runs whenever the tree stays empty, so without this the filters stack up
		if ($filterName === $this->encodingFilterName && is_resource($this->encodingFilter)) {
			return;
		}

		$this->log(WarmingMessages::ENCODING_NOT_UTF8, [], LogLevel::WARNING);

		// swapping encodings must replace the filter, not add a second one
		$this->removeEncodingFilter();

		$raised = $this->conversionErrors($encoding);

		if ([] === $raised) {
			[$filter, $raised] = $this->quietly(function () use ($filterName) {
				return stream_filter_prepend($this->stream, $filterName, STREAM_FILTER_READ);
			});
		}

		if ([] !== $raised || false === ($filter ?? false)) {
			$this->log('Unsupported encoding {encoding}, content is read as-is: {errors}', [
				'encoding' => $encoding,
				'errors'   => implode('; ', $raised),
			], LogLevel::WARNING);

			return;
		}

		$this->log('Adding encoding filter ' . $filterName);

		$this->encodingFilterName = $filterName;
		$this->encodingFilter     = $filter;
	}

	/**
	 * Runs $action with PHP's diagnostics captured rather than emitted: `@` only silences
	 * handlers that respect error_reporting(), and a strict one would turn a warning here
	 * into an exception.
	 *
	 * @return array{0: mixed, 1: string[]} the return value, and every message raised
	 */
	private function quietly(callable $action): array {
		$raised = [];

		set_error_handler(function (int $number, string $message) use (&$raised): bool {
			$raised[] = $message;

			return true;
		});

		try {
			return [$action(), $raised];
		} finally {
			restore_error_handler();
		}
	}

	/** Dropping it means the content is read as-is, which still leaves ASCII directives readable. */
	private function removeEncodingFilter(): void {
		if (is_resource($this->encodingFilter)) {
			$this->quietly(function () {
				return stream_filter_remove($this->encodingFilter);
			});
		}

		$this->encodingFilter     = null;
		$this->encodingFilterName = null;
	}

	/**
	 * iconv takes the charset name on trust and only fails once it meets the bytes - by which
	 * point the filter is stuck in the chain and stream_filter_remove() refuses to detach it.
	 * So try the conversion in memory first and simply never attach a filter that cannot work.
	 *
	 * @return string[] empty when the content converts cleanly
	 */
	private function conversionErrors(string $encoding): array {
		rewind($this->stream);
		[$sample] = $this->quietly(function () {
			return stream_get_contents($this->stream);
		});
		rewind($this->stream);

		if (!is_string($sample) || '' === $sample) {
			return [];
		}

		[$converted, $raised] = $this->quietly(function () use ($encoding, $sample) {
			return iconv($encoding, 'UTF-8', $sample);
		});

		if (false === $converted) {
			$raised[] = sprintf('cannot convert content from %s to UTF-8', $encoding);
		}

		return $raised;
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
