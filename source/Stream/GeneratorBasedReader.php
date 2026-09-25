<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Stream;

use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Config\Option;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;
use t1gor\RobotsTxtParser\RunsQuietlyTrait;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\Stream\Filters\EnsureEndOfLinesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipDirectivesWithInvalidValuesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipEndOfCommentedLineFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipCommentedLinesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipEmptyLinesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\SkipUnsupportedDirectivesFilter;
use t1gor\RobotsTxtParser\Stream\Filters\TrimSpacesLeftFilter;
use t1gor\RobotsTxtParser\WarningMessages;

class GeneratorBasedReader implements ReaderInterface {

	use LogsIfAvailableTrait;
	use RunsQuietlyTrait;

	private $stream;

	/** @var array<class-string<CustomFilterInterface>, resource|false> Applied in order. */
	private array $filters;

	/** @var resource|false|null Separate from $filters - not one of our classes. */
	private $encodingFilter = null;

	private ?string $encodingFilterName = null;

	private bool $truncated = false;

	/** Only close what we opened. */
	private bool $ownsStream = false;

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

		if ($this->ownsStream && is_resource($this->stream)) {
			fclose($this->stream);
		}
	}

	/**
	 * @param string $input
	 *
	 * @return static
	 */
	public static function fromString(string $input = '', ?Configuration $config = null): self {
		$reader = new GeneratorBasedReader();
		$stream = tmpfile();

		fwrite($stream, $input);
		fseek($stream, 0);

		$reader->log(WarningMessages::STRING_INIT_DEPRECATE);
		$reader->ownsStream = true;

		return $reader->setStream($reader->bound($stream, $config));
	}

	public static function fromStream($stream, ?Configuration $config = null): self {
		if (!is_resource($stream)) {
			$error = sprintf('Argument must be a valid resource type. %s given.', gettype($stream));
			throw new \InvalidArgumentException($error);
		}

		$reader = new GeneratorBasedReader();

		// a non-seekable stream (http) warns rather than rewinds; read on from where it stands
		$reader->quietly(function () use ($stream) {
			return rewind($stream);
		});

		return $reader->setStream($reader->bound($stream, $config));
	}

	/**
	 * Copies at most $limit raw bytes into a stream of our own. Counting ahead of every filter is
	 * what makes the limit mean fetched bytes, as Google and RFC 9309 cap them, and stops the read
	 * itself running long. No limit keeps the original stream and its lazy reads.
	 *
	 * @param resource $stream
	 *
	 * @return resource
	 */
	private function bound($stream, ?Configuration $config) {
		$limit = ($config ?? new Configuration())->byteLimit;

		if (is_null($limit)) {
			return $stream;
		}

		$bounded = tmpfile();

		// one byte past the limit tells us there was more
		$copied          = stream_copy_to_stream($stream, $bounded, $limit + 1);
		$this->truncated = (int) $copied > $limit;

		if ($this->truncated) {
			$this->trimToLastLine($bounded, $limit);
			$this->log(WarningMessages::BYTE_LIMIT_REACHED, [
				Option::BYTE_LIMIT->value => $limit,
			], LogLevel::WARNING);
		}

		if ($this->ownsStream) {
			fclose($stream);
		}

		$this->ownsStream = true;
		rewind($bounded);

		return $bounded;
	}

	/**
	 * A cut mid-line would turn "Disallow: /admin/secret" into "Disallow: /admin", so the partial
	 * line goes instead.
	 *
	 * @param resource $stream
	 */
	private function trimToLastLine($stream, int $limit): void {
		ftruncate($stream, $limit);

		$end = $limit;

		while ($end > 0) {
			$start  = max(0, $end - 8192);
			fseek($stream, $start);
			$buffer = (string) fread($stream, $end - $start);
			$at     = strrpos($buffer, "\n");

			if (false !== $at) {
				ftruncate($stream, $start + $at + 1);
				return;
			}

			$end = $start;
		}

		// no line ending anywhere: nothing here is a complete directive
		ftruncate($stream, 0);
	}

	public function wasTruncated(): bool {
		return $this->truncated;
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
				['logger' => $this->logger()] // buffered until a real one lands
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
	public function setEncoding(string $encoding): void {
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

		$this->log(WarningMessages::ENCODING_NOT_UTF8, [], LogLevel::WARNING);

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
		// the name on its own, which needs no bytes: iconv answers false for a charset it doesn't know
		[$known, $raised] = $this->quietly(function () use ($encoding) {
			return iconv($encoding, 'UTF-8', '');
		});

		if (false === $known) {
			$raised[] = sprintf('unknown charset %s', $encoding);

			return $raised;
		}

		// reading the sample consumes the document, so only where it can be put back
		if (!$this->rewindIfPossible()) {
			return [];
		}

		[$sample] = $this->quietly(function () {
			return stream_get_contents($this->stream);
		});

		$this->rewindIfPossible();

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
	 * A non-seekable stream (http, a pipe) cannot be replayed, and asking warns - so whatever
	 * reads it has to cope with reading on from where it stands.
	 *
	 * The metadata is only the cheap first answer: a userland wrapper without stream_seek() is
	 * still advertised as seekable, so the rewind itself has the last word.
	 */
	private function rewindIfPossible(): bool {
		if (true !== (stream_get_meta_data($this->stream)['seekable'] ?? false)) {
			return false;
		}

		[$rewound] = $this->quietly(function () {
			return rewind($this->stream);
		});

		return true === $rewound;
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
		$this->rewindIfPossible();

		while (!feof($this->stream)) {
			$line = fgets($this->stream);

			if (false !== $line) {
				yield $line;
			}
		}
	}

	public function getContentRaw(): string {
		$this->rewindIfPossible();

		return stream_get_contents($this->stream);
	}
}
