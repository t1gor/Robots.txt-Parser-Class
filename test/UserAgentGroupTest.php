<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use t1gor\RobotsTxtParser\RobotsTxtParser;

/**
 * A group can start with several user-agent lines; its rules apply to each of them.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9309#section-2.1
 */
class UserAgentGroupTest extends TestCase {

	public static function groupSizes(): array {
		return [
			'two user-agents'   => [['Googlebot', 'Bingbot']],
			'three user-agents' => [['Googlebot', 'Bingbot', 'Slurp']],
			'four user-agents'  => [['Googlebot', 'Bingbot', 'Slurp', 'DuckDuckBot']],
		];
	}

	/**
	 * @dataProvider groupSizes
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('groupSizes')]
	public function testRulesApplyToEveryUserAgentOfTheGroup(array $userAgents) {
		$lines = array_map(fn (string $userAgent) => 'User-agent: ' . $userAgent, $userAgents);
		$lines[] = 'Disallow: /private';

		$parser = (new RobotsTxtParser())->setContent(implode("\n", $lines));

		foreach ($userAgents as $userAgent) {
			$this->assertTrue($parser->isDisallowed('/private', $userAgent), $userAgent);
			$this->assertTrue($parser->isAllowed('/public', $userAgent), $userAgent);
		}
	}

	public function testGlobalRulesStayWhenAGroupWithTheGlobalAgentFollows() {
		$parser = (new RobotsTxtParser())->setContent(implode("\n", [
			'User-agent: *',
			'Disallow: /private',
			'',
			'User-agent: *',
			'User-agent: Slurp',
			'Disallow: /tmp',
		]));

		$this->assertTrue($parser->isDisallowed('/private', 'Googlebot'));
		$this->assertTrue($parser->isDisallowed('/tmp', 'Googlebot'));
		$this->assertTrue($parser->isDisallowed('/tmp', 'Slurp'));
	}
}
