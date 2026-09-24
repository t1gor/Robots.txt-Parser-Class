<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Config\ConfigurationFactory;
use t1gor\RobotsTxtParser\Exception\NoContentException;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessorsFactory;
use t1gor\RobotsTxtParser\Parser\TreeBuilder;
use t1gor\RobotsTxtParser\Parser\TreeBuilderInterface;
use t1gor\RobotsTxtParser\Parser\Url;
use t1gor\RobotsTxtParser\Parser\UserAgent\UserAgentMatcher;
use t1gor\RobotsTxtParser\Parser\UserAgent\UserAgentMatcherInterface;
use t1gor\RobotsTxtParser\Stream\GeneratorBasedReader;
use t1gor\RobotsTxtParser\Stream\ReaderInterface;

/**
 * Class for parsing robots.txt files
 *
 * @author Igor Timoshenkov <igor.timoshenkov@gmail.com>
 * @author Jan-Petter Gundersen <europe.jpg@gmail.com>
 *
 * Logic schema and signals:
 * @link   https://docs.google.com/document/d/1_rNjxpnUUeJG13ap6cnXM6Sx9ZQtd1ngADXnW9SHJSE
 *
 * Specifications:
 * @link   https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt
 * @link   https://yandex.com/support/webmaster/controlling-robot/robots-txt.xml
 * @link   http://www.robotstxt.org/
 * @link   http://www.w3.org/TR/html4/appendix/notes.html
 *
 * Useful links and materials:
 * @link   http://www.the-art-of-web.com/php/parse-robots/
 * @link   http://socoder.net/index.php?snippet=23824
 */
class RobotsTxtParser implements LoggerAwareInterface {

	use LogsIfAvailableTrait;

	// default encoding
	const DEFAULT_ENCODING = 'UTF-8';

	// rules set
	protected $rules = [];

	// host set
	protected $host = null;

	// robots.txt http status code
	protected ?int $httpStatusCode = null;

	// UserAgent
	private $userAgent      = '*';

	private array                      $tree = [];

	/** Matching a user-agent walks every name in the tree, so the answer is kept until the tree changes. */
	private array $matched = [];

	/** A client asks about a handful of agents; past this it has stopped being a cache, so start over. */
	private const MAX_MATCHED = 512;

	private ?array $treeUserAgents = null;

	/** Dependencies only, so a container can resolve this once and hand it round. */
	public function __construct(
		protected readonly ?Configuration $config = new Configuration(),
		protected ?TreeBuilderInterface $treeBuilder = null,
		protected ?ReaderInterface $reader = null,
		protected ?UserAgentMatcherInterface $userAgentMatcher = null
	) {
		// a hand-built Configuration has been through no checks; warnings buffer until a logger lands
		ConfigurationFactory::validate($this->config, $this->logger());

		if (!is_null($reader) && !is_null($config)) {
			// an injected reader was built with a limit of its own
			$this->log('Both a reader and a configuration were passed; the configuration is not applied to the reader.');
		}

		if (is_null($this->userAgentMatcher)) {
			$this->log('UserAgentMatcher is not passed, using a default one...');

			$this->userAgentMatcher = new UserAgentMatcher();
		}
	}

	/**
	 * Content is not a dependency, so it arrives separately - that is what lets the parser be
	 * resolved from a container and reused. Encoding travels with it, since it describes the
	 * document rather than the parser.
	 *
	 * @param resource|string $content
	 */
	public function setContent($content, ?string $encoding = null): self {
		$this->reader = is_resource($content)
			? GeneratorBasedReader::fromStream($content, $this->config)
			: GeneratorBasedReader::fromString((string) $content, $this->config);

		$this->reader->setLogger($this->logger());

		if (!is_null($encoding) && $encoding !== static::DEFAULT_ENCODING) {
			$this->reader->setEncoding($encoding);
		}

		// a new document: nothing from the last one still holds
		$this->tree           = [];
		$this->httpStatusCode = null;

		$this->forgetUserAgents();

		return $this;
	}

	/** @throws NoContentException */
	public function getReader(): ReaderInterface {
		return $this->reader();
	}

	/** @throws NoContentException */
	private function reader(): ReaderInterface {
		if (is_null($this->reader)) {
			throw new NoContentException('Nothing to parse yet - call setContent() first.');
		}

		return $this->reader;
	}

	private function buildTree(): void {
		if (!empty($this->tree)) {
			return;
		}

		// construct a tree builder if not passed
		if (is_null($this->treeBuilder)) {
			$this->log('Creating a default tree builder as none passed...');

			$this->treeBuilder = new TreeBuilder(
				DirectiveProcessorsFactory::getDefault($this->logger()),
				$this->logger()
			);
		}

		$this->treeBuilder->setContent($this->reader()->getContentIterated());
		$this->tree = $this->treeBuilder->build();

		$this->forgetUserAgents();
	}

	private function forgetUserAgents(): void {
		$this->matched        = [];
		$this->treeUserAgents = null;
	}

	/** Which name in the tree serves this user-agent. */
	private function matchUserAgent(string $userAgent): string {
		if (!isset($this->matched[$userAgent])) {
			if (count($this->matched) >= self::MAX_MATCHED) {
				$this->matched = [];
			}

			$this->treeUserAgents ??= array_keys($this->tree);

			$this->matched[$userAgent] = $this->userAgentMatcher->getMatching($userAgent, $this->treeUserAgents);
		}

		return $this->matched[$userAgent];
	}

	public function getConfiguration(): Configuration {
		return $this->config;
	}

	public function getLogger(): LoggerInterface {
		return $this->logger();
	}

	protected function onLoggerSet(LoggerInterface $logger): void {
		if ($this->reader instanceof LoggerAwareInterface) {
			$this->reader->setLogger($logger);
		}

		if ($this->userAgentMatcher instanceof LoggerAwareInterface) {
			$this->userAgentMatcher->setLogger($logger);
		}
	}

	/**
	 * Set the HTTP status code
	 *
	 * @param int $code
	 *
	 * @return bool
	 */
	public function setHttpStatusCode(int $code): bool {
		if (!is_int($code) || $code < 100 || $code > 599) {
			$this->log('Invalid HTTP status code, not taken into account.', ['code' => $code], LogLevel::WARNING);
			return false;
		}

		$this->httpStatusCode = $code;

		return true;
	}

	public function isAllowed(string $url, ?string $userAgent = '*'): bool {
		$this->buildTree();

		$url = new Url($url);
		$url->setLogger($this->logger());

		return $this->checkRules(Directive::ALLOW, $url->getPath(), $userAgent);
	}

	/**
	 * Check rules
	 *
	 * Of all the matching rules the most specific one wins - the one with the most octets - so the
	 * order they appear in the file is irrelevant. Ties go to allow, which also makes `Allow: /`
	 * alongside `Disallow: /` resolve to allowed.
	 *
	 * @link https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2
	 * @link https://yandex.com/support/webmaster/controlling-robot/robots-txt.xml#simultaneous
	 *
	 * @param string $rule      - rule to check
	 * @param string $path      - path to check
	 * @param string $userAgent - which robot to check for
	 *
	 * @return bool
	 */
	protected function checkRules(string $rule, string $path, string $userAgent = '*'): bool {
		// check for disallowed http status code
		if ($this->checkHttpStatusCodeRule()) {
			return ($rule === Directive::DISALLOW);
		}

		$userAgent = $this->matchUserAgent($userAgent);
		$winner    = null;
		$longest   = -1;

		// allow goes last so that it takes an equally specific disallow over on a tie
		foreach ([Directive::DISALLOW, Directive::ALLOW] as $directive) {
			if (!isset($this->tree[$userAgent][$directive])) {
				continue;
			}

			foreach ($this->tree[$userAgent][$directive] as $robotRule) {
				if ($this->checkRuleSwitch($robotRule, $path) && strlen($robotRule) >= $longest) {
					$longest = strlen($robotRule);
					$winner  = $directive;
				}
			}
		}

		if (is_null($winner)) {
			$this->log(strtr('No rule matched {path}, allowed by default', ['{path}' => $path]));
		}

		// nothing matched - allowed by default
		return is_null($winner)
			? ($rule === Directive::ALLOW)
			: ($rule === $winner);
	}

	/**
	 * Check HTTP status code rule
	 *
	 * @return bool
	 */
	private function checkHttpStatusCodeRule(): bool {
		if (isset($this->httpStatusCode) && $this->httpStatusCode >= 500 && $this->httpStatusCode <= 599) {
			$this->log("Disallowed by HTTP status code {$this->httpStatusCode}");
			return true;
		}

		return false;
	}

	/**
	 * Only allow/disallow paths reach here - inline clean-param/host are kept out of the tree
	 * by their own processors, see {@see getCleanParam()} and {@see getHost()}.
	 */
	protected function checkRuleSwitch(string $rule, string $path): bool {
		return $this->checkBasicRule($rule, $path);
	}

	/**
	 * Check basic rule
	 */
	private function checkBasicRule(string $rule, string $path): bool {
		if (preg_match('@' . $this->prepareRegexRule($rule) . '@', $path)) {
			$this->log('Rule match: Path');
			return true;
		}

		return false;
	}

	/**
	 * Only `*` (wildcard) and a trailing `$` (end anchor) are special; everything else is a literal,
	 * so quote it rather than hand-escaping a list that keeps missing metachars.
	 *
	 * @link https://www.rfc-editor.org/rfc/rfc9309#section-2.2.2
	 */
	protected function prepareRegexRule(string $value): string {
		$anchored = mb_substr($value, -1) === '$';

		if ($anchored) {
			$value = mb_substr($value, 0, -1);
		}

		// both sides of the comparison must be percent-encoded, and getPath() already encodes the path
		$quoted = implode('.*', array_map(function (string $literal): string {
			return preg_quote(Url::encode($literal), '@');
		}, explode('*', $value)));

		// no end anchor means prefix matching, which '^' alone already gives us
		return '^' . $quoted . ($anchored ? '$' : '');
	}

	/**
	 * Check url wrapper
	 *
	 * @param string      $url       - url to check
	 * @param string|null $userAgent - which robot to check for
	 *
	 * @return bool
	 */
	public function isDisallowed(string $url, string $userAgent = '*'): bool {
		$this->buildTree();

		$url = new Url($url);
		$url->setLogger($this->logger());

		return $this->checkRules(Directive::DISALLOW, $url->getPath(), $userAgent);
	}

	public function getDelay(string $userAgent = "*", string $type = Directive::CRAWL_DELAY) {
		$this->buildTree();

		$directive = in_array($type, [Directive::CACHE, Directive::CACHE_DELAY])
			? Directive::CACHE_DELAY
			: Directive::CRAWL_DELAY;

		if (isset($this->tree[$userAgent][$directive])) {
			// return delay for requested directive
			return $this->tree[$userAgent][$directive];
		}

		if (isset($this->tree[$userAgent][Directive::CRAWL_DELAY])) {
			$this->log("{$directive} directive (unofficial): Not found, fallback to " . Directive::CRAWL_DELAY . " directive");
			return $this->tree[$userAgent][Directive::CRAWL_DELAY];
		}

		$this->log("$directive directive: Not found");

		return 0;
	}

	public function getCleanParam(): array {
		$this->buildTree();

		if (!isset($this->tree[Directive::CLEAN_PARAM]) || empty($this->tree[Directive::CLEAN_PARAM])) {
			$this->log(Directive::CLEAN_PARAM . ' directive: Not found');
		}

		return $this->tree[Directive::CLEAN_PARAM];
	}

	/**
	 * Render
	 *
	 * @param string $eol
	 *
	 * @return string
	 */
	public function render($eol = "\r\n") {
		$input = $this->getRules();
		krsort($input);
		$output = [];
		foreach ($input as $userAgent => $rules) {
			$output[] = 'User-agent: ' . $userAgent;
			foreach ($rules as $directive => $value) {
				// Not multibyte
				$directive = ucfirst($directive);
				if (is_array($value)) {
					// Shorter paths later; a bool return is deprecated for usort() since PHP 8.3
					usort($value, function ($a, $b) {
						return mb_strlen($b) <=> mb_strlen($a);
					});
					foreach ($value as $subValue) {
						$output[] = $directive . ': ' . $subValue;
					}
				} else {
					$output[] = $directive . ': ' . $value;
				}
			}
			$output[] = '';
		}

		$host = $this->getHost();
		if ($host !== null) {
			$output[] = 'Host: ' . $host;
		}

		$sitemaps = $this->getSitemaps();
		foreach ($sitemaps as $sitemap) {
			$output[] = 'Sitemap: ' . $sitemap;
		}

		$output[] = '';
		return implode($eol, $output);
	}

	public function getRules(?string $userAgent = null) {
		$this->buildTree();

		// return all rules
		if ($userAgent === null) {
			return $this->tree;
		}

		$userAgent = $this->matchUserAgent($userAgent);

		// direct match
		if (isset($this->tree[$userAgent])) {
			return $this->tree[$userAgent];
		}

		// fallback for *
		if (isset($this->tree['*'])) {
			$this->log(sprintf("No direct match found for '%s', fallback to *", $userAgent));
			return $this->tree['*'];
		}

		$this->log(sprintf("Rules not found for the given User-Agent '%s'", $userAgent));

		return [];
	}

	/**
	 * @param ?string $userAgent
	 *
	 * @note NULL is returned to public API compatibility reasons. Will be removed in the future.
	 *
	 * @return string[]|string|null
	 */
	public function getHost(?string $userAgent = null) {
		$this->buildTree();

		if (!is_null($userAgent)) {
			$userAgent = $this->matchUserAgent($userAgent);

			if (isset($this->tree[$userAgent][Directive::HOST]) && !empty($this->tree[$userAgent][Directive::HOST])) {
				return $this->tree[$userAgent][Directive::HOST];
			}

			return null;
		}

		$hosts = [];

		foreach ($this->tree as $userAgentBased) {
			if (isset($userAgentBased[Directive::HOST]) && !empty($userAgentBased[Directive::HOST])) {
				array_push($hosts, $userAgentBased[Directive::HOST]);
			}
		}

		return !empty($hosts) ? $hosts : null;
	}

	public function getSitemaps(?string $userAgent = null): array {
		$this->buildTree();
		$maps = [];

		if (!is_null($userAgent)) {
			$userAgent = $this->matchUserAgent($userAgent);

			if (isset($this->tree[$userAgent][Directive::SITEMAP]) && !empty($this->tree[$userAgent][Directive::SITEMAP])) {
				return $this->tree[$userAgent][Directive::SITEMAP];
			}
		} else {
			foreach ($this->tree as $userAgentBased) {
				if (isset($userAgentBased[Directive::SITEMAP]) && !empty($userAgentBased[Directive::SITEMAP])) {
					$maps = array_merge($maps, $userAgentBased[Directive::SITEMAP]);
				}
			}
		}

		return $maps;
	}
}
