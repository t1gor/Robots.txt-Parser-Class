<?php declare(strict_types=1);

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Configuration;
use t1gor\RobotsTxtParser\Exception\NoContentException;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * A container builds a service once, from its dependencies, and hands it round - so the content
 * cannot be a constructor argument.
 *
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::__construct
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::setContent
 * @covers \t1gor\RobotsTxtParser\RobotsTxtParser::reader
 */
class ContainerResolutionTest extends TestCase {

	/** What autowiring actually needs: nothing required, and nothing it would have to invent. */
	public function testEveryConstructorArgumentIsOptionalAndATypeAContainerCanResolve() {
		$parameters = (new \ReflectionClass(RobotsTxtParser::class))->getConstructor()->getParameters();

		$this->assertNotEmpty($parameters);

		foreach ($parameters as $parameter) {
			$type = $parameter->getType();

			$this->assertTrue($parameter->isOptional(), $parameter->getName() . ' is required');
			$this->assertInstanceOf(\ReflectionNamedType::class, $type);
			$this->assertFalse($type->isBuiltin(), $parameter->getName() . ' is a scalar');
		}
	}

	public function testItIsUsableWithNoArgumentsAtAll() {
		$parser = (new RobotsTxtParser())->setContent("User-agent: *\nDisallow: /admin\n");

		$this->assertTrue($parser->isDisallowed('/admin'));
		$this->assertSame(Configuration::DEFAULT_BYTE_LIMIT, $parser->getConfiguration()->byteLimit);
	}

	public function testSetContentIsFluent() {
		$this->assertInstanceOf(RobotsTxtParser::class, (new RobotsTxtParser())->setContent(''));
	}

	/** The point of the split: build once, parse many. */
	public function testOneParserHandlesSeveralDocumentsWithoutBleeding() {
		$parser = new RobotsTxtParser();

		$parser->setContent("User-agent: *\nDisallow: /first\n");
		$this->assertTrue($parser->isDisallowed('/first'));
		$this->assertTrue($parser->isAllowed('/second'));

		$parser->setContent("User-agent: *\nDisallow: /second\n");
		$this->assertTrue($parser->isDisallowed('/second'));
		$this->assertTrue($parser->isAllowed('/first'), 'rules from the previous document leaked');
	}

	/** A 5xx from the last site must not disallow the next one. */
	public function testHttpStatusDoesNotSurviveIntoTheNextDocument() {
		$parser = new RobotsTxtParser();

		$parser->setContent("User-agent: *\nAllow: /\n");
		$parser->setHttpStatusCode(503);
		$this->assertTrue($parser->isDisallowed('/anything'));

		$parser->setContent("User-agent: *\nAllow: /\n");
		$this->assertTrue($parser->isAllowed('/anything'));
	}

	public function testEncodingTravelsWithTheContentRatherThanTheParser() {
		$parser = new RobotsTxtParser();

		$parser->setContent(fopen(__DIR__ . '/Fixtures/cp1251-real-bytes.txt', 'r'), 'Windows-1251');
		$this->assertSame(['/каталог', '/поиск'], $parser->getRules()['*']['disallow']);

		$parser->setContent("User-agent: *\nDisallow: /plain\n");
		$this->assertSame(['/plain'], $parser->getRules()['*']['disallow']);
	}

	public function testAskingAnythingBeforeSettingContentFails() {
		$this->expectException(NoContentException::class);
		$this->expectExceptionMessage('call setContent() first');

		(new RobotsTxtParser())->getRules();
	}

	/** A container wires the logger up front, long before any content arrives. */
	public function testALoggerSetBeforeTheContentStillReachesTheReader() {
		$handler = new TestHandler(LogLevel::DEBUG);
		$logger  = new Logger(static::class);
		$logger->pushHandler($handler);

		$parser = new RobotsTxtParser();
		$parser->setLogger($logger);
		$parser->setContent(fopen(__DIR__ . '/Fixtures/with-commented-lines.txt', 'r'));
		$parser->getRules();

		$this->assertTrue(
			$handler->hasRecordThatContains('lines skipped as commented out', Level::Debug),
			stringifyLogs($handler->getRecords())
		);
	}

	/** Configuration is a dependency, so it stays on the constructor where a container can inject it. */
	public function testTheInjectedConfigurationAppliesToEveryDocument() {
		$parser = new RobotsTxtParser(new Configuration(Configuration::RECOMMENDED_MIN_BYTE_LIMIT));

		foreach (['first', 'second'] as $round) {
			$parser->setContent("User-agent: *\nDisallow: /{$round}\n"
				. str_repeat("Disallow: /padding\n", 2000));

			$this->assertTrue($parser->getReader()->wasTruncated(), $round . ' was not bounded');
		}
	}
}
