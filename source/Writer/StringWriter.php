<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Writer;

use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\WarningMessages;

/**
 * Builds the whole document, encodes it, then writes it in one go.
 *
 * The simple one: it holds the finished robots.txt, so a conversion either works for all of it or
 * for none of it. {@see StreamWriter} trades that for not holding it at all.
 */
class StringWriter extends AbstractWriter {

	public function render(): int {
		$rendered = '';

		foreach ($this->lines() as $line) {
			$rendered .= $line;
		}

		[$written] = $this->quietly(fn () => fwrite($this->output(), $this->encode($rendered)));

		return (int) $written;
	}

	/**
	 * The tree is UTF-8, so anything else is a conversion on the way out - and a warning, since
	 * the spec asks for UTF-8. A conversion that cannot work leaves the document as it was, the
	 * way {@see \t1gor\RobotsTxtParser\Stream\GeneratorBasedReader::setEncoding()} reads as-is rather than failing.
	 */
	protected function encode(string $rendered): string {
		if ($this->isUtf8($this->encoding())) {
			return $rendered;
		}

		$this->log(WarningMessages::ENCODING_NOT_UTF8, [], LogLevel::WARNING);

		[$converted, $raised] = $this->quietly(fn () => iconv('UTF-8', $this->encoding(), $rendered));

		if (is_string($converted)) {
			return $converted;
		}

		$this->log(strtr('Unsupported encoding {encoding}, the document stays UTF-8: {errors}', [
			'{encoding}' => $this->encoding(),
			'{errors}'   => implode('; ', $raised),
		]), [], LogLevel::WARNING);

		return $rendered;
	}
}
