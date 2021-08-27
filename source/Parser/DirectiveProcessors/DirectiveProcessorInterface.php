<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use Psr\Log\LoggerInterface;

interface DirectiveProcessorInterface {

	public function __construct(?LoggerInterface $logger = null);

	public function getDirectiveName(): string;

	public function matches(string $line): bool;

	public function process(string $line, array & $root, string & $currentUserAgent = '*', string $prevLine = '');
}
