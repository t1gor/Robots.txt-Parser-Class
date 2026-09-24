<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Writer;

/**
 * Builds the whole document, converts it, then writes it in one go.
 *
 * The one to reach for by default: a real robots.txt is kilobytes, and this is both simpler and
 * ~10% quicker for one write instead of one per line. It peaks at roughly one and a half times the
 * document, so {@see StreamWriter} is the answer once the size is large or not yours to control.
 */
class StringWriter extends AbstractWriter {

	public function render(): int {
		$rendered = '';

		foreach ($this->lines() as $line) {
			$rendered .= $line;
		}

		// one handler swap around the whole thing, not one per call inside it
		[$written, $raised] = $this->quietly(fn (): int => $this->write($this->output(), $this->convert($rendered)));

		$this->report($raised);

		return $written;
	}
}
