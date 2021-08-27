<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessorsFactory;
use t1gor\RobotsTxtParser\Parser\HostName;
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
	protected ?int $httpStatusCode;

	// url
	private $url = null;

	// UserAgent
	private $userAgent      = '*';

	// robots.txt file content
	private        $content  = '';
	private string $encoding = '';

	private array                      $tree = [];
	private ?ReaderInterface           $reader;
	private ?TreeBuilderInterface      $treeBuilder;
	private ?UserAgentMatcherInterface $userAgentMatcher;

	public function __construct(
		$content,
		string $encoding = self::DEFAULT_ENCODING,
		?TreeBuilderInterface $treeBuilder = null,
		?ReaderInterface $reader = null,
		?UserAgentMatcherInterface $userAgentMatcher = null
	) {
		$this->treeBuilder      = $treeBuilder;
		$this->reader           = $reader;
		$this->encoding         = $encoding;
		$this->userAgentMatcher = $userAgentMatcher;

		if (is_null($this->reader)) {
			$this->log('Reader is not passed, using a default one...');

			$this->reader = is_resource($content)
				? GeneratorBasedReader::fromStream($content)
				: GeneratorBasedReader::fromString($content);
		}

		if (is_null($this->userAgentMatcher)) {
			$this->log('UserAgentMatcher is not passed, using a default one...');

			$this->userAgentMatcher = new UserAgentMatcher();
		}
	}

	private function buildTree() {
		if (!empty($this->tree)) {
			return;
		}

		if ($this->encoding !== static::DEFAULT_ENCODING) {
			$this->reader->setEncoding($this->encoding);
		}

		// construct a tree builder if not passed
		if (is_null($this->treeBuilder)) {
			$this->log('Creating a default tree builder as none passed...');

			$this->treeBuilder = new TreeBuilder(
				DirectiveProcessorsFactory::getDefault($this->logger),
				$this->logger
			);
		}

		$this->treeBuilder->setContent($this->reader->getContentIterated());
		$this->tree = $this->treeBuilder->build();
	}

	public function getLogger(): ?LoggerInterface {
		return $this->logger;
	}

	public function setLogger(LoggerInterface $logger): void {
		$this->logger = $logger;

		if ($this->reader instanceof LoggerAwareInterface) {
			$this->reader->setLogger($this->logger);
		}

		if ($this->userAgentMatcher instanceof LoggerAwareInterface) {
			$this->userAgentMatcher->setLogger($this->logger);
		}
	}

	private static function isValidHostName(string $host): bool {
		return HostName::isValid($host);
	}

	/**
	 * Validate URL scheme
	 *
	 * @param string $scheme
	 *
	 * @return bool
	 */
	private static function isValidScheme($scheme) {
		return Url::isValidScheme($scheme);
	}

	/**
	 * Parse URL
	 *
	 * @param string $url
	 *
	 * @return array|false
	 */
	protected function parseURL($url) {
		$parsed = parse_url($url);
		if ($parsed === false) {
			return false;
		} elseif (!isset($parsed['scheme']) || !$this->isValidScheme($parsed['scheme'])) {
			return false;
		} else {
			if (!isset($parsed['host']) || !$this->isValidHostName($parsed['host'])) {
				return false;
			} else {
				if (!isset($parsed['port'])) {
					$parsed['port'] = getservbyname($parsed['scheme'], 'tcp');
					if (!is_int($parsed['port'])) {
						return false;
					}
				}
			}
		}
		$parsed['custom'] = (isset($parsed['path']) ? $parsed['path'] : '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
		return $parsed;
	}

	/**
	 * Explode Clean-Param rule
	 *
	 * @param string $rule
	 *
	 * @return array
	 */
	private function explodeCleanParamRule($rule) {
		// strip multi-spaces
		$rule = preg_replace('/\s+/S', ' ', $rule);
		// split into parameter and path
		$array      = explode(' ', $rule, 2);
		$cleanParam = [];
		// strip any invalid characters from path prefix
		$cleanParam['path'] = isset($array[1]) ? $this->encode_url(preg_replace('/[^A-Za-z0-9\.-\/\*\_]/', '', $array[1])) : '/*';
		$param              = explode('&', $array[0]);
		foreach ($param as $key) {
			$cleanParam['param'][] = trim($key);
		}
		return $cleanParam;
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
		!is_null($this->logger) && $url->setLogger($this->logger);

		return $this->checkRules(Directive::ALLOW, $url->getPath(), $userAgent);
	}

	/**
	 * Set UserAgent
	 *
	 * @param string $userAgent
	 *
	 * @return void
	 * @deprecated please check rules for exact user agent instead
	 */
	public function setUserAgent(string $userAgent) {
		throw new \RuntimeException(WarmingMessages::SET_UA_DEPRECATED);
	}

	/**
	 * Check rules
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

		// Check each directive for rules, allowed by default
		$result    = ($rule === Directive::ALLOW);
		$userAgent = $this->userAgentMatcher->getMatching($userAgent, array_keys($this->tree));

		foreach ([Directive::DISALLOW, Directive::ALLOW] as $directive) {
			if (!isset($this->tree[$userAgent][$directive])) {
				continue;
			}

			foreach ($this->tree[$userAgent][$directive] as $robotRule) {
				// check rule
				if ($this->checkRuleSwitch($robotRule, $path)) {
					// rule match
					$result = ($rule === $directive);
				}
			}
		}

		return $result;
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

	protected function checkRuleSwitch(string $rule, string $path): bool {
		switch (Directive::attemptGetInline($rule)) {

			case Directive::CLEAN_PARAM:
				if ($this->checkCleanParamRule(Directive::stripInline($rule), $path)) {
					return true;
				}
				break;

			case Directive::HOST;
				if ($this->checkHostRule(Directive::stripInline($rule))) {
					return true;
				}
				break;

			default:
				return $this->checkBasicRule($rule, $path);
		}
	}

	/**
	 * Check Clean-Param rule
	 *
	 * @param string $rule
	 * @param string $path
	 *
	 * @return bool
	 */
	private function checkCleanParamRule($rule, $path) {
		$cleanParam = $this->explodeCleanParamRule($rule);
		// check if path prefix matches the path of the url we're checking
		if (!$this->checkBasicRule($cleanParam['path'], $path)) {
			return false;
		}
		foreach ($cleanParam['param'] as $param) {
			if (!strpos($path, "?$param=")
				&& !strpos($path, "&$param=")
			) {
				return false;
			}
		}
		$this->log('Rule match: ' . Directive::CLEAN_PARAM . ' directive');
		return true;
	}

	/**
	 * Check basic rule
	 */
	private function checkBasicRule(string $rule, string $path): bool {
		// change @ to \@
		$escaped = strtr($this->prepareRegexRule($rule), ['@' => '\@']);

		// match result
		if (preg_match('@' . $escaped . '@', $path)) {
			$this->log('Rule match: Path');
			return true;
		}

		return false;
	}

	protected function prepareRegexRule(string $value): string {
		$escape = ['$' => '\$', '?' => '\?', '.' => '\.', '*' => '.*', '[' => '\[', ']' => '\]'];
		$value  = str_replace(array_keys($escape), array_values($escape), $value);

		if (mb_strlen($value) > 2 && mb_substr($value, -2) == '\$') {
			$value = substr($value, 0, -2) . '$';
		}

		if (mb_strrpos($value, '/') == (mb_strlen($value) - 1)
			|| mb_strrpos($value, '=') == (mb_strlen($value) - 1)
			|| mb_strrpos($value, '?') == (mb_strlen($value) - 1)
		) {
			$value .= '.*';
		}

		if (substr($value, 0, 2) != '.*') {
			$value = '^' . $value;
		}
		return $value;
	}

	/**
	 * Check Host rule
	 *
	 * @param string $rule
	 *
	 * @return bool
	 */
	private function checkHostRule($rule) {
		if (!isset($this->url)) {
			$error_msg = WarmingMessages::INLINED_HOST;
			$this->log($error_msg, [], LogLevel::ERROR);
			return false;
		}

		$url  = $this->parseURL($this->url);
		$host = trim(str_ireplace(Directive::HOST . ':', '', mb_strtolower($rule)));
		if (in_array(
			$host, [
				$url['host'],
				$url['host'] . ':' . $url['port'],
				$url['scheme'] . '://' . $url['host'],
				$url['scheme'] . '://' . $url['host'] . ':' . $url['port'],
			]
		)) {
			$this->log('Rule match: ' . Directive::HOST . ' directive');
			return true;
		}
		return false;
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
		!is_null($this->logger) && $url->setLogger($this->logger);

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
	 * @deprecated
	 */
	public function getContent(): string {
		return $this->content;
	}

	/**
	 * @return array
	 * @deprecated
	 * @see RobotsTxtParser::getLogger()
	 */
	public function getLog(): array {
		return [];
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
					// Shorter paths later
					usort($value, function ($a, $b) {
						return mb_strlen($a) < mb_strlen($b);
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

		$userAgent = $this->userAgentMatcher->getMatching($userAgent, array_keys($this->tree));

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
			$userAgent = $this->userAgentMatcher->getMatching($userAgent, array_keys($this->tree));

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
			$userAgent = $this->userAgentMatcher->getMatching($userAgent, array_keys($this->tree));

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
