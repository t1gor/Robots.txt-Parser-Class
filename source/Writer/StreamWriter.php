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
 * Each line is converted on its own, so a stateful encoding restarts its state per line; robots.txt
 * directives are ASCII, so nothing here depends on that state.
 */
class StreamWriter extends AbstractWriter {

	public function render(): int {
		// one handler swap for the whole render: per line it cost more than the writing did
		[$written, $raised] = $this->quietly(function (): int {
			$output  = $this->output();
			$written = 0;

			foreach ($this->lines() as $line) {
				$written += $this->write($output, $this->convert($line));
			}

			return $written;
		});

		$this->report($raised);

		return $written;
	}
}
