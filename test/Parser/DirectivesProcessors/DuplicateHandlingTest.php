<?php declare(strict_types=1);

namespace Parser\DirectivesProcessors;

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\DisallowProcessor;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * Duplicates are dropped through an index rather than a scan, so the cases where the index could
 * fall out of step with the tree are what needs covering.
 *
 * @covers \t1gor\RobotsTxtParser\Parser\DirectiveProcessors\DeduplicatesEntriesTrait
 */
class DuplicateHandlingTest extends TestCase {

	public function testRepeatedValuesAreKeptOnce() {
		$parser = (new RobotsTxtParser())->setContent(implode("\n", [
			'User-agent: *',
			'Disallow: /admin',
			'Allow: /admin/public',
			'Disallow: /admin',
			'Allow: /admin/public',
			'Sitemap: https://example.test/sitemap.xml',
			'Sitemap: https://example.test/sitemap.xml',
		]));

		$rules = $parser->getRules('*');

		$this->assertSame(['/admin'], $rules[Directive::DISALLOW]);
		$this->assertSame(['/admin/public'], $rules[Directive::ALLOW]);
		$this->assertSame(['https://example.test/sitemap.xml'], $rules[Directive::SITEMAP]);
	}

	/** A processor is handed the tree, so it cannot assume it filled every entry in it. */
	public function testPreFilledTreeIsRespected() {
		$processor = new DisallowProcessor();
		$userAgent = 'Googlebot';
		$tree      = [$userAgent => [Directive::DISALLOW => ['/already-here']]];

		$processor->process('Disallow: /already-here', $tree, $userAgent);
		$processor->process('Disallow: /new', $tree, $userAgent);

		$this->assertSame(['/already-here', '/new'], $tree[$userAgent][Directive::DISALLOW]);
	}

	/** The tree builder is kept between documents; what the last one held must not carry over. */
	public function testSecondDocumentStartsClean() {
		$parser = new RobotsTxtParser();

		$parser->setContent("User-agent: *\nDisallow: /a\n");
		$this->assertSame(['/a'], $parser->getRules('*')[Directive::DISALLOW]);

		$parser->setContent("User-agent: *\nDisallow: /a\nDisallow: /b\n");
		$this->assertSame(['/a', '/b'], $parser->getRules('*')[Directive::DISALLOW]);
	}

	/**
	 * A group past DeduplicatesEntriesTrait::INDEX_FROM swaps the scan for an index, so repeats
	 * from either side of that line have to be caught - and the index must not outlive the document.
	 */
	public function testBigGroupsStillDropRepeats() {
		$lines = ['User-agent: *'];

		for ($i = 0; $i < 400; $i++) {
			$lines[] = 'Disallow: /path/' . $i;
		}

		// one from below the threshold, two from above it
		array_push($lines, 'Disallow: /path/10', 'Disallow: /path/300', 'Disallow: /path/399');

		$content = implode("\n", $lines);
		$parser  = (new RobotsTxtParser())->setContent($content);
		$rules   = $parser->getRules('*')[Directive::DISALLOW];

		$this->assertCount(400, $rules);
		$this->assertSame(array_values(array_unique($rules)), $rules);

		$parser->setContent($content);

		$this->assertCount(400, $parser->getRules('*')[Directive::DISALLOW], 'the index outlived the document');
	}

	/** Stacked user-agents share one rule set, so a repeat under either name is still a repeat. */
	public function testLinkedUserAgentsShareTheirRules() {
		$parser = (new RobotsTxtParser())->setContent(implode("\n", [
			'User-agent: Googlebot',
			'User-agent: Bingbot',
			'Disallow: /admin',
			'',
			'User-agent: Googlebot',
			'Disallow: /admin',
			'Disallow: /tmp',
		]));

		$this->assertSame(['/admin', '/tmp'], $parser->getRules('Googlebot')[Directive::DISALLOW]);
		$this->assertSame(['/admin', '/tmp'], $parser->getRules('Bingbot')[Directive::DISALLOW]);
	}
}
