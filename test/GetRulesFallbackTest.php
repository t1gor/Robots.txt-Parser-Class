<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;
use t1gor\RobotsTxtParser\Parser\UserAgent\UserAgentMatcherInterface;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * The bundled matcher only ever answers with a tree key or '*', so getRules() can never reach its
 * '*' fallback through it. An injected matcher is under no such obligation - this pins the
 * behaviour the fallback exists for.
 *
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::getRules
 */
class GetRulesFallbackTest extends TestCase {

	private function parserWithMatcherReturning(string $answer): RobotsTxtParser {
		$matcher = new class implements UserAgentMatcherInterface {

			use LogsIfAvailableTrait;

			/** The interface fixes the constructor signature, so this is set after construction. */
			public string $answer = '*';

			public function __construct(?LoggerInterface $logger = null) {
				$this->logger = $logger;
			}

			public function getMatching(string $userAgent, array $available = []): string {
				return $this->answer;
			}
		};

		$matcher->answer = $answer;

		$log = new Logger(static::class);
		$log->pushHandler(new TestHandler(LogLevel::DEBUG));

		$parser = new RobotsTxtParser(userAgentMatcher: $matcher);
		$parser->setContent("User-Agent: *\nDisallow: /everyone\n\nUser-Agent: GoogleBot\nDisallow: /google\n");
		$parser->setLogger($log);

		return $parser;
	}

	public function testFallsBackToStarWhenTheMatcherNamesSomethingAbsent() {
		$parser = $this->parserWithMatcherReturning('NotInTheTree');

		$this->assertSame(['disallow' => ['/everyone']], $parser->getRules('whoever'));

		/** @var TestHandler $handler */
		$handler = $parser->getLogger()->getHandlers()[0];

		$this->assertTrue(
			$handler->hasRecord("No direct match found for 'NotInTheTree', fallback to *", Level::Debug),
			stringifyLogs($handler->getRecords())
		);
	}

	public function testReturnsTheDirectMatchWhenTheMatcherNamesOne() {
		$parser = $this->parserWithMatcherReturning('GoogleBot');

		$this->assertSame(['disallow' => ['/google']], $parser->getRules('whoever'));
	}

	public function testReturnsNothingWhenNeitherTheNameNorStarIsPresent() {
		$matcher = new class implements UserAgentMatcherInterface {

			use LogsIfAvailableTrait;

			public function __construct(?LoggerInterface $logger = null) {
				$this->logger = $logger;
			}

			public function getMatching(string $userAgent, array $available = []): string {
				return 'NotInTheTree';
			}
		};

		$parser = new RobotsTxtParser(userAgentMatcher: $matcher);
		$parser->setContent("User-Agent: GoogleBot\nDisallow: /google\n");

		$this->assertSame([], $parser->getRules('whoever'));
	}
}
