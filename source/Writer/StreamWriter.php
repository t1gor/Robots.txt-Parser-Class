<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Writer;

use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\WarningMessages;

/**
 * Writes the document out as it is produced, rather than building it first.
 *
 * Same normalising as {@see Writer} - it only differs in when the output is written, and in how the
 * encoding is applied: PHP's own iconv filter converts each chunk on the way to the stream, the way
 * the reader converts on the way in. So a character with no spelling in the target encoding is
 * reported by the filter as the write happens, instead of the whole document falling back to UTF-8.
 */
class StreamWriter extends AbstractWriter {

	/** A line at a time, so nothing bigger than a line is ever held. */
	public function render(): int {
		$output  = $this->output();
		$filter  = $this->encodingFilter($output);
		$written = 0;

		foreach ($this->lines() as $line) {
			[$bytes] = $this->quietly(fn () => fwrite($output, $line));

			$written += (int) $bytes;
		}

		// the stream is the caller's, so leave it as we found it
		if (is_resource($filter)) {
			$this->quietly(fn () => stream_filter_remove($filter));
		}

		return $written;
	}

	/**
	 * Null when the stream needs no conversion, or when one was asked for that cannot work - then
	 * the document goes out as UTF-8, which still leaves the ASCII directives readable.
	 *
	 * @return resource|null
	 */
	private function encodingFilter($output) {
		$encoding = $this->encoding();

		if ($this->isUtf8($encoding)) {
			return null;
		}

		$this->log(WarningMessages::ENCODING_NOT_UTF8, [], LogLevel::WARNING);

		// iconv takes the charset name on trust, so try it before a filter can get stuck in the chain
		[$probe, $raised] = $this->quietly(fn () => iconv('UTF-8', $encoding, 'probe'));

		if (is_string($probe)) {
			[$filter, $raised] = $this->quietly(
				fn () => stream_filter_append($output, 'convert.iconv.utf-8/' . $encoding, STREAM_FILTER_WRITE)
			);
		}

		if (is_resource($filter ?? null)) {
			return $filter;
		}

		$this->log(strtr('Unsupported encoding {encoding}, the document stays UTF-8: {errors}', [
			'{encoding}' => $encoding,
			'{errors}'   => implode('; ', $raised),
		]), [], LogLevel::WARNING);

		return null;
	}
}
