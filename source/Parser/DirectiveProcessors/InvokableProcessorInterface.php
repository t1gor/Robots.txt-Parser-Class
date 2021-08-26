<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser\DirectiveProcessors;

use Psr\Log\LoggerInterface;

interface InvokableProcessorInterface {

	public function __construct(?LoggerInterface $logger = null);

	public function getDirectiveName(): string;

	public function __invoke(string $line, array & $root, string & $currentUserAgent = '*');
}
