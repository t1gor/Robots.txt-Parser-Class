<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Writer;

use Psr\Log\LoggerAwareInterface;
use t1gor\RobotsTxtParser\Exception\NoOutputException;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * Everything a render needs is set first; then render() writes it out and says how much it wrote.
 *
 * @see Writer       builds the document, then writes it in one go
 * @see StreamWriter writes it a line at a time, never holding more than one
 */
interface WriterInterface extends LoggerAwareInterface {

	/** What a robots.txt is served with; see RFC 9309 section 2.2.1. */
	const DEFAULT_EOL = "\r\n";

	/** @param array $tree the rules tree, shaped as {@see RobotsTxtParser::getRules()} returns it */
	public function setTree(array $tree): static;

	public function setEol(string $eol): static;

	/** @param ?string $encoding what to write out as; null keeps the UTF-8 the spec asks for */
	public function setEncoding(?string $encoding): static;

	/**
	 * @param resource $output where the document goes
	 *
	 * @throws \InvalidArgumentException when it is not a stream
	 */
	public function setOutput($output): static;

	/**
	 * @return int bytes handed to the output
	 *
	 * @throws NoOutputException when there is nowhere to write
	 */
	public function render(): int;
}
