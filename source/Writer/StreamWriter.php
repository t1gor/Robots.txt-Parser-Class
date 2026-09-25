<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Writer;

/**
 * Writes the document out as it is produced, rather than building it first.
 *
 * Same normalising and the same encoding rule as {@see StringWriter}; it only differs in holding a
 * line instead of the document. That peaks at about half the document against the other's one and a
 * half times, for 10-17% more time - worth it once the file is large or its size is not yours to
 * control, and a wash below ~10k rules. See bin/benchmark-writers.php.
 *
 * Each line is converted on its own, so a stateful encoding restarts its state per line. Directives
 * are ASCII, so nothing depends on that state except the byte order mark UTF-16 and UTF-32 open
 * with - stripped after the first line, so both writers still emit the same bytes.
 */
class StreamWriter extends AbstractWriter {

	public function render(): int {
		// one handler swap for the whole render: per line it cost more than the writing did
		[$written, $raised] = $this->quietly(function (): int {
			$output   = $this->output();
			$written  = 0;
			$preamble = null;

			foreach ($this->lines() as $line) {
				$bytes = $this->convert($line);

				// the first line carries it; on the rest it would be a mark mid-document
				if (is_null($preamble)) {
					$preamble = $this->preamble();
				} elseif ('' !== $preamble && str_starts_with($bytes, $preamble)) {
					$bytes = substr($bytes, strlen($preamble));
				}

				$written += $this->write($output, $bytes);
			}

			return $written;
		});

		$this->report($raised);

		return $written;
	}
}
