<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Writer;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Exception\EncodingFailedException;
use t1gor\RobotsTxtParser\Exception\NoOutputException;
use t1gor\RobotsTxtParser\Exception\WriteFailedException;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;
use t1gor\RobotsTxtParser\Parser\HostName;
use t1gor\RobotsTxtParser\Parser\RequestRate;
use t1gor\RobotsTxtParser\Parser\TimeWindow;
use t1gor\RobotsTxtParser\Parser\Url;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\RunsQuietlyTrait;
use t1gor\RobotsTxtParser\WarningMessages;

/**
 * Everything both writers share: the rules tree in, a normalised robots.txt out, line by line.
 * Only where those lines go, and how they are encoded, is left to {@see StringWriter} and
 * {@see StreamWriter}.
 *
 * Normalising rather than echoing: values that cannot be valid are dropped and logged, duplicates
 * go, directive names get their canonical casing, the file-wide directives are hoisted into one
 * block at the end, and user-agents carrying the same rules share a single group. Everything
 * dropped is reported, so a caller that wants to know why its file shrank can read the log.
 *
 * @link https://www.rfc-editor.org/rfc/rfc9309
 */
abstract class AbstractWriter implements WriterInterface {

	use LogsIfAvailableTrait;
	use RunsQuietlyTrait;

	/** Would break the line, or be eaten as a comment, once written out. */
	private const UNSAFE = '/[\x00-\x1F\x7F#]/';

	/** As above, plus empty, plus a colon - which would be read as the end of the directive. */
	private const UNUSABLE_NAME = '/^$|[:#\x00-\x1F\x7F]/';

	/** A rule is a path, and has to survive being read back. */
	private const PATH = '/^\/[^#\x00-\x1F\x7F]*$/';

	/** @link https://yandex.com/support/webmaster/robot-workings/clean-param.html */
	private const PARAM_NAME = '/^[A-Za-z0-9._~-]+$/';

	/** Which revision of the extended standard a group is written to. */
	private const VERSION = '/^\d+(\.\d+)*$/';

	/** File-wide directives, collected while walking the groups and written once. */
	private array $hosts = [];
	private array $sitemaps = [];
	private array $cleanParams = [];

	private array $tree = [];
	private string $eol = self::DEFAULT_EOL;
	private ?string $encoding = null;

	/** @var resource|null */
	private $output = null;

	public function __construct(?LoggerInterface $logger = null) {
		$this->logger = $logger;
	}

	public function setTree(array $tree): static {
		$this->tree = $tree;

		return $this;
	}

	public function setEol(string $eol): static {
		$this->eol = $eol;

		return $this;
	}

	public function setEncoding(?string $encoding): static {
		// null once it is settled, so the hot path is a null check rather than this test per line
		$this->encoding = $this->isUtf8($encoding) ? null : $encoding;

		// said once here, as the reader says it when the conversion is set up rather than used
		if (!is_null($this->encoding)) {
			$this->log(WarningMessages::ENCODING_NOT_UTF8, [], LogLevel::WARNING);
		}

		return $this;
	}

	public function setOutput($output): static {
		if (!is_resource($output)) {
			throw new \InvalidArgumentException(
				sprintf('Argument must be a valid resource type. %s given.', gettype($output))
			);
		}

		$this->output = $output;

		return $this;
	}

	/** @return resource */
	protected function output() {
		if (!is_resource($this->output)) {
			throw new NoOutputException('Nowhere to write to - call setOutput() first.');
		}

		return $this->output;
	}

	/** @return int bytes handed to the output */
	abstract public function render(): int;

	/**
	 * The tree is UTF-8, so anything else is a conversion on the way out. A conversion that cannot
	 * work throws rather than quietly writing UTF-8: bytes served under a charset they are not in,
	 * or a rule missing from a policy file, are both worse than a failed render.
	 *
	 * Callers keep PHP's own diagnostics quiet around this - see {@see RunsQuietlyTrait::quietly()}.
	 *
	 * @throws EncodingFailedException
	 */
	protected function convert(string $text): string {
		if (is_null($this->encoding)) {
			return $text;
		}

		$converted = iconv('UTF-8', $this->encoding, $text);

		if (!is_string($converted)) {
			throw new EncodingFailedException(strtr(
				'Cannot write this robots.txt as {encoding}: the charset is unknown, or the content '
				. 'has characters it cannot represent.',
				['{encoding}' => (string) $this->encoding]
			));
		}

		return $converted;
	}

	/**
	 * @return int bytes the output took, which has to be all of them
	 *
	 * @throws WriteFailedException
	 */
	protected function write($output, string $bytes): int {
		$count = fwrite($output, $bytes);

		// false, or short: either way what is out there is not what was asked for
		if (!is_int($count) || $count < strlen($bytes)) {
			throw new WriteFailedException(sprintf(
				'Output took %s of %d bytes.',
				var_export($count, true),
				strlen($bytes)
			));
		}

		return $count;
	}

	/** Whatever PHP raised while writing; captured so a strict handler cannot turn it into a throw. */
	protected function report(array $raised): void {
		foreach ($raised as $message) {
			$this->log('While writing: ' . $message, [], LogLevel::WARNING);
		}
	}

	/**
	 * The document a line at a time, UTF-8, each one already terminated - so a caller can push it
	 * somewhere instead of holding it.
	 *
	 * Groups are still built in full before the first line: merging the user-agents that share a
	 * rule set, and putting the catch-all last, cannot be decided until every group has been seen.
	 */
	protected function lines(): \Generator {
		$this->hosts = $this->sitemaps = $this->cleanParams = [];

		$tree = $this->tree;

		$this->collectCleanParams($tree[Directive::CLEAN_PARAM->value] ?? []);

		// the only tree key that is not a user-agent
		unset($tree[Directive::CLEAN_PARAM->value]);

		$groups = $this->groups($tree);

		// filled while the groups were walked: these apply to the whole file, wherever they were written
		$trailer = $this->trailer();

		if ([] === $groups && [] === $trailer) {
			$this->log('Nothing valid left to render, returning an empty document.', [], LogLevel::WARNING);

			return;
		}

		// a blank line opens every block but the first
		$separator = '';

		foreach ($groups as $group) {
			foreach ($group['agents'] as $agent) {
				yield $separator . Directive::USERAGENT->label() . ': ' . $agent . $this->eol;

				$separator = '';
			}

			foreach ($group['rules']['extras'] as $extra) {
				yield $extra . $this->eol;
			}

			yield from $this->ordered($group['rules']['allow'], $group['rules']['disallow']);

			foreach ($group['rules']['noindex'] as $path) {
				yield Directive::NOINDEX->label() . ': ' . $path . $this->eol;
			}

			foreach ($group['rules']['delays'] as $delay) {
				yield $delay . $this->eol;
			}

			$separator = $this->eol;
		}

		foreach ($trailer as $line) {
			yield $separator . $line . $this->eol;

			$separator = '';
		}
	}

	/** One entry per distinct rule set, with every user-agent that shares it. */
	private function groups(array $tree): array {
		$byRules = [];

		foreach ($tree as $name => $rules) {
			$agent = $this->agentName((string) $name);

			if (is_null($agent)) {
				continue;
			}

			$lines = $this->rules(is_array($rules) ? $rules : [], $agent);

			if ([] === array_filter($lines)) {
				$this->log("Nothing left to write for {$agent}, the group is dropped.");

				continue;
			}

			$key = $this->groupKey($lines);

			// a digest collision would merge two unrelated groups, so the rules already stored settle it
			while (isset($byRules[$key]) && $byRules[$key]['rules'] !== $lines) {
				$key .= '!';
			}

			$byRules[$key]['agents'][] = $agent;
			$byRules[$key]['rules']    = $lines;
		}

		return $this->sortGroups($byRules);
	}

	/**
	 * What decides that two user-agents carry the same rules. A digest rather than the rule text:
	 * as an array key the text would be held a second time, which doubled peak memory on a big file.
	 */
	protected function groupKey(array $lines): string {
		$digest = hash_init('xxh128');

		// fed a piece at a time: joining first would hold the whole rule set a second time
		array_walk_recursive($lines, static function (string $value) use ($digest): void {
			hash_update($digest, $value . "\n");
		});

		return hash_final($digest);
	}

	/** Null when the name could not be written back out and read the same way. */
	private function agentName(string $name): ?string {
		$trimmed = trim($name);

		if (1 === preg_match(self::UNUSABLE_NAME, $trimmed)) {
			$this->log(strtr('User-agent "{name}" dropped as not a usable name.', [
				'{name}' => $name,
			]), [], LogLevel::WARNING);

			return null;
		}

		return $trimmed;
	}

	/**
	 * One group's rules, kept as the tree's own path strings - sorted, but neither prefixed nor
	 * concatenated. Formatting here would be a second copy of every path; {@see ordered()} builds
	 * each line as it is written instead. Host and sitemap are taken out as they are file-wide.
	 *
	 * @return array{extras: string[], allow: string[], disallow: string[], noindex: string[], delays: string[]}
	 */
	private function rules(array $rules, string $agent): array {
		$paths  = [Directive::ALLOW->value => [], Directive::DISALLOW->value => [], Directive::NOINDEX->value => []];
		$delays = [];
		$extras = [];

		foreach ($rules as $directive => $value) {
			// tryFrom() rather than a string compare: anything the enum does not know falls through
			$case = Directive::tryFrom(strtolower(trim((string) $directive)));

			match ($case) {
				Directive::ALLOW,
				Directive::DISALLOW,
				Directive::NOINDEX     => $paths[$case->value] = $this->paths($case, $value, $agent),
				Directive::CRAWL_DELAY,
				Directive::CACHE_DELAY => $delays = array_merge($delays, $this->delay($case, $value, $agent)),
				Directive::ROBOT_VERSION,
				Directive::VISIT_TIME,
				Directive::REQUEST_RATE,
				Directive::COMMENT     => $extras[$case->value] = $this->extras($case, $value, $agent),
				Directive::HOST        => $this->collectHosts($value, $agent),
				Directive::SITEMAP     => $this->collectSitemaps($value, $agent),
				default                => $this->log(strtr('{directive} is not a directive this library writes, dropped for {agent}.', [
					'{directive}' => $directive,
					'{agent}'     => $agent,
				]), [], LogLevel::WARNING),
			};
		}

		return [
			'extras'   => $this->inWrittenOrder($extras),
			'allow'    => $this->sorted($paths[Directive::ALLOW->value]),
			'disallow' => $this->sorted($paths[Directive::DISALLOW->value]),
			'noindex'  => $this->sorted($paths[Directive::NOINDEX->value]),
			'delays'   => $delays,
		];
	}

	/**
	 * The group's own metadata, in a fixed order rather than the tree's, so two trees with the same
	 * rules render the same.
	 *
	 * @return string[]
	 */
	private function inWrittenOrder(array $extras): array {
		$lines = [];

		foreach ([Directive::ROBOT_VERSION, Directive::VISIT_TIME, Directive::REQUEST_RATE, Directive::COMMENT] as $directive) {
			$lines = array_merge($lines, $extras[$directive->value] ?? []);
		}

		return $lines;
	}

	/** @return string[] the directive's lines, dropping whatever could not be read back */
	private function extras(Directive $directive, mixed $values, string $agent): array {
		$kept = [];

		// in the order given, duplicates and all - which one survives below depends on it
		foreach ($this->listed($values) as $entry) {
			$value = $this->normalised($directive, $entry);

			if (is_null($value)) {
				$this->log(strtr('{directive} "{value}" dropped for {agent} as invalid.', [
					'{directive}' => $directive->label(),
					'{value}'     => $entry,
					'{agent}'     => $agent,
				]), [], LogLevel::WARNING);

				continue;
			}

			$kept[] = $directive->label() . ': ' . $value;
		}

		// what the parser would hold: the last usable value, or one line per distinct value - the
		// canonical form deduplicating what the raw ones would not, e.g. "1/300" and "1/5m"
		return $directive->isRepeatable()
			? array_values(array_unique($kept))
			: array_slice($kept, -1);
	}

	/** One value in the form it is written out in, or null when it cannot be written at all. */
	private function normalised(Directive $directive, string $entry): ?string {
		$value = match ($directive) {
			Directive::REQUEST_RATE  => RequestRate::tryParse($entry),
			Directive::VISIT_TIME    => TimeWindow::tryParse($entry),
			Directive::ROBOT_VERSION => 1 === preg_match(self::VERSION, $entry) ? $entry : null,
			default                  => '' === $entry || $this->isUnsafe($entry) ? null : $entry,
		};

		return is_null($value) ? null : (string) $value;
	}

	/** @return string[] the tree's own strings, so the list costs a pointer per rule and no copies */
	private function paths(Directive $directive, mixed $values, string $agent): array {
		$kept = [];

		foreach (array_unique($this->listed($values)) as $path) {
			if (1 !== preg_match(self::PATH, $path)) {
				$this->log(strtr('{directive} "{value}" dropped for {agent}: a rule has to be a path.', [
					'{directive}' => $directive->value,
					'{value}'     => $path,
					'{agent}'     => $agent,
				]), [], LogLevel::WARNING);

				continue;
			}

			$kept[] = $path;
		}

		return $kept;
	}

	/**
	 * Longest first, then alphabetically. Sorting moves pointers, so this copies no path.
	 *
	 * Two scalar comparisons rather than one of a pair of arrays: the tidier `[-strlen($a), $a] <=>
	 * [-strlen($b), $b]` builds two arrays on every comparison, which measured twice as slow.
	 */
	private function sorted(array $paths): array {
		usort($paths, static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));

		return $paths;
	}

	/**
	 * The two lists merged into the order the spec resolves them in: longest rule first, and allow
	 * ahead of an equally long disallow, so a reader that stops at the first match still lands on
	 * the same answer. Each line is built as it is yielded and not before.
	 *
	 * @link https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2
	 */
	private function ordered(array $allow, array $disallow): \Generator {
		// the same two prefixes for every rule in the file, so build them before the loop
		$allows    = Directive::ALLOW->label() . ': ';
		$disallows = Directive::DISALLOW->label() . ': ';
		$a         = 0;
		$d         = 0;

		while (isset($allow[$a]) || isset($disallow[$d])) {
			$takeAllow = isset($allow[$a])
				&& (!isset($disallow[$d]) || strlen($allow[$a]) >= strlen($disallow[$d]));

			// the line ending goes on here rather than at the yield site: one allocation, not two
			yield $takeAllow
				? $allows . $allow[$a++] . $this->eol
				: $disallows . $disallow[$d++] . $this->eol;
		}
	}

	/** @return string[] the single delay line, or nothing */
	private function delay(Directive $directive, mixed $value, string $agent): array {
		$number = is_scalar($value)
			? filter_var(trim((string) $value), FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION)
			: false;

		if (false === $number || $number < 0) {
			$this->log(strtr('{directive} dropped for {agent} as not a positive number.', [
				'{directive}' => $directive->value,
				'{agent}'     => $agent,
			]), [], LogLevel::WARNING);

			return [];
		}

		// PHP already writes a whole float as "5" rather than "5.0"
		return [$directive->label() . ': ' . $number];
	}

	private function collectHosts(mixed $values, string $agent): void {
		foreach ($this->listed($values) as $host) {
			if (!HostName::isValid($host)) {
				$this->log(strtr('Host "{value}" dropped for {agent} as not a valid hostname.', [
					'{value}' => $host,
					'{agent}' => $agent,
				]), [], LogLevel::WARNING);

				continue;
			}

			$this->hosts[$host] = true;
		}
	}

	private function collectSitemaps(mixed $values, string $agent): void {
		foreach ($this->listed($values) as $sitemap) {
			if (!$this->isAbsoluteUrl($sitemap)) {
				$this->log(strtr('Sitemap "{value}" dropped for {agent}: an absolute URL is required.', [
					'{value}' => $sitemap,
					'{agent}' => $agent,
				]), [], LogLevel::WARNING);

				continue;
			}

			$this->sitemaps[$sitemap] = true;
		}
	}

	/** Tree shape is path => param names, see {@see RobotsTxtParser::getCleanParam()}. */
	private function collectCleanParams(mixed $params): void {
		foreach ((array) $params as $path => $names) {
			$given = $this->listed($names);
			$kept  = array_values(array_unique(array_filter(
				$given,
				static fn (string $name): bool => 1 === preg_match(self::PARAM_NAME, $name)
			)));
			$path  = trim((string) $path);

			if (!str_starts_with($path, '/') || empty($kept)) {
				$this->log(strtr('Clean-param for "{path}" dropped as invalid.', ['{path}' => $path]), [], LogLevel::WARNING);

				continue;
			}

			if (count($kept) !== count($given)) {
				$this->log(strtr('Some Clean-param names for "{path}" dropped as not param names.', [
					'{path}' => $path,
				]), [], LogLevel::WARNING);
			}

			$this->cleanParams[$path] = $kept;
		}
	}

	/** Host is file-wide, so a file can only mean one; Clean-param and Sitemap stack. */
	private function trailer(): array {
		$hosts = array_keys($this->hosts);

		if (count($hosts) > 1) {
			$this->log(strtr('Several hosts found ({hosts}), but Host applies to the whole file - keeping the first.', [
				'{hosts}' => implode(', ', $hosts),
			]), [], LogLevel::WARNING);
		}

		$lines = array_map(
			static fn (string $host): string => Directive::HOST->label() . ': ' . $host,
			array_slice($hosts, 0, 1)
		);

		foreach ($this->cleanParams as $path => $names) {
			$lines[] = Directive::CLEAN_PARAM->label() . ': ' . implode('&', $names) . ' ' . $path;
		}

		foreach (array_keys($this->sitemaps) as $sitemap) {
			$lines[] = Directive::SITEMAP->label() . ': ' . $sitemap;
		}

		return $lines;
	}

	/** A directive value, however the tree spells it, as trimmed strings. */
	private function listed(mixed $values): array {
		return array_map(static fn ($value): string => trim((string) $value), array_filter((array) $values, 'is_scalar'));
	}

	/** Url reduces exactly what a sitemap has to be - a known scheme and a real host - to a path. */
	private function isAbsoluteUrl(string $url): bool {
		return (new Url($url))->isReducedToPath() && !$this->isUnsafe($url);
	}

	/** "utf8", "UTF-8", "utf_8", nothing at all - all the same thing, and nothing to convert. */
	private function isUtf8(?string $encoding): bool {
		return in_array(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $encoding)), ['', 'UTF8'], true);
	}

	private function isUnsafe(string $value): bool {
		return 1 === preg_match(self::UNSAFE, $value);
	}

	/** Named agents alphabetically, the catch-all last, both within a group and between them. */
	private function sortGroups(array $byRules): array {
		$groups = array_values($byRules);

		foreach ($groups as &$group) {
			usort($group['agents'], $this->compareAgents(...));
		}

		unset($group);

		usort($groups, fn (array $a, array $b): int => $this->compareAgents($a['agents'][0], $b['agents'][0]));

		return $groups;
	}

	private function compareAgents(string $a, string $b): int {
		return ['*' === $a, strtolower($a)] <=> ['*' === $b, strtolower($b)];
	}
}
