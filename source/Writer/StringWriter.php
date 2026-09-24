<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Writer;

/**
 * Builds the whole document, converts it, then writes it in one go.
 *
 * The simple one, and the quicker one by a few percent - a single write instead of one per line.
 * {@see StreamWriter} trades that for never holding the document.
 */
class StringWriter extends AbstractWriter {

	public function render(): int {
		$rendered = '';

		foreach ($this->lines() as $line) {
			$rendered .= $line;
		}

		// one handler swap for the whole write, not one per call inside it
		[$written, $raised] = $this->quietly(fn (): int => $this->write($this->output(), $this->convert($rendered)));

		$this->report($raised);

		return $written;
	}
}
