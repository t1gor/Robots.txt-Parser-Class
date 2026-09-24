<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser\Writer;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Directive;
use t1gor\RobotsTxtParser\Exception\NoOutputException;
use t1gor\RobotsTxtParser\LogsIfAvailableTrait;
use t1gor\RobotsTxtParser\Parser\HostName;
use t1gor\RobotsTxtParser\Parser\Url;
use t1gor\RobotsTxtParser\RobotsTxtParser;
use t1gor\RobotsTxtParser\RunsQuietlyTrait;

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
		$this->encoding = $encoding;

		return $this;
	}

	protected function encoding(): ?string {
		return $this->encoding;
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

			foreach ($group['rules'] as $rule) {
				yield $rule . $this->eol;
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

			if (empty($lines)) {
				$this->log("Nothing left to write for {$agent}, the group is dropped.");

				continue;
			}

			$key                       = implode("\n", $lines);
			$byRules[$key]['agents'][] = $agent;
			$byRules[$key]['rules']    = $lines;
		}

		return $this->sortGroups($byRules);
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

	/** The directive lines of one group; host and sitemap are taken out of it as they are file-wide. */
	private function rules(array $rules, string $agent): array {
		$paths  = [];
		$delays = [];

		foreach ($rules as $directive => $value) {
			// tryFrom() rather than a string compare: anything the enum does not know falls through
			$case = Directive::tryFrom(strtolower(trim((string) $directive)));

			match ($case) {
				Directive::ALLOW,
				Directive::DISALLOW    => $paths = array_merge($paths, $this->paths($case, $value, $agent)),
				Directive::CRAWL_DELAY,
				Directive::CACHE_DELAY => $delays = array_merge($delays, $this->delay($case, $value, $agent)),
				Directive::HOST        => $this->collectHosts($value, $agent),
				Directive::SITEMAP     => $this->collectSitemaps($value, $agent),
				default                => $this->log(strtr('{directive} is not a directive this library writes, dropped for {agent}.', [
					'{directive}' => $directive,
					'{agent}'     => $agent,
				]), [], LogLevel::WARNING),
			};
		}

		return array_merge($this->pathLines($paths), $delays);
	}

	/** @return array<int, array{directive: Directive, path: string}> kept apart so {@see pathLines()} can sort them */
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

			$kept[] = ['directive' => $directive, 'path' => $path];
		}

		return $kept;
	}

	/**
	 * Longest rule first, and allow before an equally long disallow - the order the spec resolves
	 * them in, so a reader that stops at the first match still lands on the same answer.
	 *
	 * @link https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2
	 */
	private function pathLines(array $paths): array {
		// negated length rather than swapped operands, so every key reads $a on the left
		usort($paths, static fn (array $a, array $b): int =>
			[-strlen($a['path']), $a['directive']->value, $a['path']]
			<=> [-strlen($b['path']), $b['directive']->value, $b['path']]);

		return array_map(
			static fn (array $rule): string => $rule['directive']->label() . ': ' . $rule['path'],
			$paths
		);
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
	protected function isUtf8(?string $encoding): bool {
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
