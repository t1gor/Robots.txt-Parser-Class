<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Parser;

use Psr\Log\LoggerInterface;

interface TreeBuilderInterface {
	public function __construct(array $processors, ?LoggerInterface $logger);
	public function build(): array;
	public function setContent(\Iterator $content): void;
}
