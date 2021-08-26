<?php declare(strict_types=1);

namespace t1gor\RobotsTxtParser;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\AllowProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CacheDelayProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CleanParamProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\CrawlDelayProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\DisallowProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\HostProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\SitemapProcessor;
use t1gor\RobotsTxtParser\Parser\DirectiveProcessors\UserAgentProcessor;
use t1gor\RobotsTxtParser\Parser\TreeBuilder;
use t1gor\RobotsTxtParser\Parser\TreeBuilderInterface;
use t1gor\RobotsTxtParser\Stream\Reader;
use vipnytt\UserAgentParser;

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

	// states
	const STATE_ZERO_POINT     = 'zero-point';
	const STATE_READ_DIRECTIVE = 'read-directive';
	const STATE_SKIP_SPACE     = 'skip-space';
	const STATE_SKIP_LINE      = 'skip-line';
	const STATE_READ_VALUE     = 'read-value';

	// rules set
	protected $rules = [];

	// host set
	protected $host = null;

	// robots.txt http status code
	protected $httpStatusCode = null;

	/**
	 * @var array
	 * @deprecated
	 */
	protected array $log = [];

	// internally used variables
	protected $current_UserAgent  = [];
	protected $current_word       = '';
	protected $current_char       = '';
	protected $char_index         = 0;
	protected $current_directive  = '';
	protected $previous_directive = '';

	// current state
	private $state = '';

	// url
	private $url = null;

	// UserAgent
	private $userAgent      = '*';
	private $userAgentMatch = '*';

	// robots.txt file content
	private $content = '';

	private Reader $reader;
	private array $tree = [];
	private ?TreeBuilderInterface $treeBuilder;

	public function __construct(
		$content,
		string $encoding = self::DEFAULT_ENCODING,
		?TreeBuilderInterface $treeBuilder = null
	) {
		$this->reader = is_resource($content)
			? Reader::fromStream($content)
			: Reader::fromString($content);

		$this->treeBuilder = $treeBuilder;
	}

	private function buildTree() {
		if (!empty($this->tree)) {
			return;
		}

		// construct a tree builder if not passed
		if (is_null($this->treeBuilder)) {
			$this->log('Creating a default tree builder as none passed...');

			$processors = [
				new UserAgentProcessor($this->logger),
				new CrawlDelayProcessor($this->logger),
				new CacheDelayProcessor($this->logger),
				new HostProcessor($this->logger),
				new CleanParamProcessor($this->logger),
				new SitemapProcessor($this->logger),
				new AllowProcessor($this->logger),
				new DisallowProcessor($this->logger),
			];

			$this->treeBuilder = new TreeBuilder($processors, $this->logger);
		}

		$this->treeBuilder->setContent($this->reader->getContent());
		$this->tree = $this->treeBuilder->build();
	}

	public function getLogger(): ?LoggerInterface {
		return $this->logger;
	}

	public function setLogger(LoggerInterface $logger): void {
		$this->logger = $logger;

		if ($this->reader) {
			$this->reader->setLogger($this->logger);
		}
	}

	/**
	 * Machine step
	 *
	 * @return void
	 */
	protected function step() {
		switch ($this->state) {
			case self::STATE_ZERO_POINT:
				$this->zeroPoint();
				break;

			case self::STATE_READ_DIRECTIVE:
				$this->readDirective();
				break;

			case self::STATE_SKIP_SPACE:
				$this->skipSpace();
				break;

			case self::STATE_SKIP_LINE:
				$this->skipLine();
				break;

			case self::STATE_READ_VALUE:
				$this->readValue();
				break;
		}
	}

	/**
	 * Process state ZERO_POINT
	 *
	 * @return RobotsTxtParser
	 */
	protected function zeroPoint() {
		if ($this->shouldSwitchToZeroPoint()) {
			$this->switchState(self::STATE_READ_DIRECTIVE);
		} elseif ($this->newLine()) {
			// unknown directive - skip it
			$this->current_word = '';
			$this->increment();
		} else {
			$this->increment();
		}
		return $this;
	}

	/**
	 * Check if we should switch
	 *
	 * @return bool
	 */
	protected function shouldSwitchToZeroPoint() {
		return in_array(mb_strtolower($this->current_word), Directive::getAll(), true);
	}

	/**
	 * Change state
	 *
	 * @param string $stateTo - state that should be set
	 *
	 * @return void
	 */
	protected function switchState($stateTo = self::STATE_SKIP_LINE) {
		$this->state = $stateTo;
	}

	/**
	 * Move to new line signal
	 */
	protected function newLine() {
		return in_array(
			"\n", [
				$this->current_char,
				$this->current_word,
			]
		);
	}

	/**
	 * Move to the following step
	 *
	 * @return void
	 */
	protected function increment() {
		$this->current_char = mb_substr($this->content, $this->char_index, 1);
		$this->current_word .= $this->current_char;
		$this->current_word = ltrim($this->current_word);
		$this->char_index++;
	}

	/**
	 * Read directive
	 *
	 * @return RobotsTxtParser
	 */
	protected function readDirective() {
		$this->previous_directive = $this->current_directive;
		$this->current_directive  = mb_strtolower(trim($this->current_word));

		$this->increment();

		if ($this->lineSeparator()) {
			$this->current_word = '';
			$this->switchState(self::STATE_READ_VALUE);
		} else {
			if ($this->space()) {
				$this->switchState(self::STATE_SKIP_SPACE);
			}
			if ($this->sharp()) {
				$this->switchState(self::STATE_SKIP_LINE);
			}
		}
		return $this;
	}

	/**
	 * Key : value pair separator signal
	 */
	protected function lineSeparator() {
		return ($this->current_char == ':');
	}

	/**
	 * "Space" signal
	 */
	protected function space() {
		return ($this->current_char == "\s");
	}

	/**
	 * Comment signal (#)
	 */
	protected function sharp() {
		return ($this->current_char == '#');
	}

	/**
	 * Skip space
	 *
	 * @return RobotsTxtParser
	 */
	protected function skipSpace() {
		$this->char_index++;
		$this->current_word = mb_substr($this->current_word, -1);
		return $this;
	}

	/**
	 * Skip line
	 *
	 * @return RobotsTxtParser
	 */
	protected function skipLine() {
		$this->char_index++;
		$this->switchState(self::STATE_ZERO_POINT);
		return $this;
	}

	/**
	 * Read value
	 *
	 * @return RobotsTxtParser
	 */
	protected function readValue() {
		if ($this->newLine()) {
			$this->addValueToDirective();
		} elseif ($this->sharp()) {
			$this->current_word = mb_substr($this->current_word, 0, -1);
			$this->addValueToDirective();
		} else {
			$this->increment();
		}
		return $this;
	}

	/**
	 * Add value to directive
	 *
	 * @return void
	 */
	private function addValueToDirective() {
		$this->convert('trim');
		switch ($this->current_directive) {

			case Directive::ALLOW:
			case Directive::DISALLOW:
				$this->addRule();
				break;
		}
		// clean-up
		$this->current_word = '';
		$this->switchState(self::STATE_ZERO_POINT);
	}

	/**
	 * Convert wrapper
	 *
	 * @param array|string $convert
	 *
	 * @return void
	 */
	private function convert($convert) {
		$this->current_word = call_user_func($convert, $this->current_word);
	}

	/**
	 * Add group-member rule
	 *
	 * @param bool $append
	 *
	 * @return void
	 */
	private function addRule($append = true) {
		if (empty($this->current_word)) {
			return;
		}
		foreach ($this->current_UserAgent as $ua) {
			if ($append === true) {
				$this->rules[$ua][$this->current_directive][] = $this->current_word;
				continue;
			}
			$this->rules[$ua][$this->current_directive] = $this->current_word;
		}
	}

	/**
	 * Add Host
	 *
	 * @return void
	 */
	private function addHost() {
		$parsed = parse_url($this->encode_url($this->current_word));
		if (isset($this->host) || $parsed === false) {
			return;
		}
		$host = isset($parsed['host']) ? $parsed['host'] : $parsed['path'];
		if (!$this->isValidHostName($host)) {
			return;
		} elseif (isset($parsed['scheme']) && !$this->isValidScheme($parsed['scheme'])) {
			return;
		}
		$scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
		$port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
		if ($this->current_word == $scheme . $host . $port) {
			$this->host = $this->current_word;
		}
	}

	/**
	 * URL encoder according to RFC 3986
	 * Returns a string containing the encoded URL with disallowed characters converted to their percentage encodings.
	 *
	 * @link http://publicmind.in/blog/url-encoding/
	 *
	 * @param string $url
	 *
	 * @return string string
	 */
	protected static function encode_url($url) {
		$reserved = [
			':' => '!%3A!ui',
			'/' => '!%2F!ui',
			'?' => '!%3F!ui',
			'#' => '!%23!ui',
			'[' => '!%5B!ui',
			']' => '!%5D!ui',
			'@' => '!%40!ui',
			'!' => '!%21!ui',
			'$' => '!%24!ui',
			'&' => '!%26!ui',
			"'" => '!%27!ui',
			'(' => '!%28!ui',
			')' => '!%29!ui',
			'*' => '!%2A!ui',
			'+' => '!%2B!ui',
			',' => '!%2C!ui',
			';' => '!%3B!ui',
			'=' => '!%3D!ui',
			'%' => '!%25!ui',
		];
		$url      = preg_replace(array_values($reserved), array_keys($reserved), rawurlencode($url));
		return $url;
	}

	/**
	 * Validate host name
	 *
	 * @link   http://stackoverflow.com/questions/1755144/how-to-validate-domain-name-in-php
	 *
	 * @param string $host
	 *
	 * @return bool
	 */
	private static function isValidHostName($host) {
		return (preg_match('/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i', $host) // valid chars check
			&& preg_match('/^.{1,253}$/', $host) // overall length check
			&& preg_match('/^[^\.]{1,63}(\.[^\.]{1,63})*$/', $host) // length of each label
			&& !filter_var($host, FILTER_VALIDATE_IP)); // is not an IP address
	}

	/**
	 * Validate URL scheme
	 *
	 * @param string $scheme
	 *
	 * @return bool
	 */
	private static function isValidScheme($scheme) {
		return in_array(
			$scheme, [
				'http',
				'https',
				'ftp',
				'sftp',
			]
		);
	}

	/**
	 * Add Sitemap
	 *
	 * @return void
	 */
	private function addSitemap() {
		$parsed = $this->parseURL($this->encode_url($this->current_word));
		if ($parsed !== false) {
			$this->sitemap[] = $this->current_word;
			$this->sitemap   = array_unique($this->sitemap);
		}
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
	 * Add Clean-Param record
	 *
	 * @return void
	 */
	private function addCleanParam() {
		$cleanParam = $this->explodeCleanParamRule($this->current_word);
		foreach ($cleanParam['param'] as $param) {
			$this->cleanparam[$cleanParam['path']][] = $param;
			$this->cleanparam[$cleanParam['path']]   = array_unique($this->cleanparam[$cleanParam['path']]);
		}
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
	public function setHttpStatusCode($code) {
		$code = intval($code);
		if (!is_int($code)
			|| $code < 100
			|| $code > 599
		) {
			trigger_error('Invalid HTTP status code, not taken into account.', E_USER_WARNING);
			return false;
		}
		$this->httpStatusCode = $code;
		return true;
	}

	/**
	 * Check url wrapper
	 *
	 * @param string      $url       - url to check
	 * @param string|null $userAgent - which robot to check for
	 *
	 * @return bool
	 */
	public function isAllowed($url, $userAgent = null) {
		if ($userAgent !== null) {
			$this->setUserAgent($userAgent);
		}
		$url = $this->encode_url($url);
		return $this->checkRules(Directive::ALLOW, $this->getPath($url), $this->userAgentMatch);
	}

	/**
	 * Set UserAgent
	 *
	 * @param string $userAgent
	 *
	 * @return void
	 */
	public function setUserAgent($userAgent) {
		$this->userAgent      = $userAgent;
		$uaParser             = new UserAgentParser($this->userAgent);
		$this->userAgentMatch = $uaParser->getMostSpecific(array_keys($this->rules));
		if (!$this->userAgentMatch) {
			$this->userAgentMatch = '*';
		}
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
	protected function checkRules($rule, $path, $userAgent) {
		// check for disallowed http status code
		if ($this->checkHttpStatusCodeRule()) {
			return ($rule === Directive::DISALLOW);
		}
		// Check each directive for rules, allowed by default
		$result = ($rule === Directive::ALLOW);
		foreach ([Directive::DISALLOW, Directive::ALLOW] as $directive) {
			if (isset($this->rules[$userAgent][$directive])) {
				foreach ($this->rules[$userAgent][$directive] as $robotRule) {
					// check rule
					if ($this->checkRuleSwitch($robotRule, $path)) {
						// rule match
						$result = ($rule === $directive);
					}
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
	private function checkHttpStatusCodeRule() {
		if (isset($this->httpStatusCode)
			&& $this->httpStatusCode >= 500
			&& $this->httpStatusCode <= 599
		) {
			$this->log[] = 'Disallowed by HTTP status code 5xx';
			return true;
		}
		return false;
	}

	/**
	 * Check rule switch
	 *
	 * @param string $rule - rule to check
	 * @param string $path - path to check
	 *
	 * @return bool
	 */
	protected function checkRuleSwitch($rule, $path) {
		switch ($this->isInlineDirective($rule)) {
			case Directive::CLEAN_PARAM:
				if ($this->checkCleanParamRule($this->stripInlineDirective($rule), $path)) {
					return true;
				}
				break;
			case Directive::HOST;
				if ($this->checkHostRule($this->stripInlineDirective($rule))) {
					return true;
				}
				break;
			default:
				if ($this->checkBasicRule($rule, $path)) {
					return true;
				}
		}
		return false;
	}

	/**
	 * Check if the rule contains a inline directive
	 *
	 * @param string $rule
	 *
	 * @return string|false
	 */
	protected function isInlineDirective($rule) {
		foreach (Directive::getAll() as $directive) {
			if (0 === strpos(mb_strtolower($rule), $directive . ':')) {
				return $directive;
			}
		}
		return false;
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
		$this->log[] = 'Rule match: ' . Directive::CLEAN_PARAM . ' directive';
		return true;
	}

	/**
	 * Check basic rule
	 *
	 * @param string $rule
	 * @param string $path
	 *
	 * @return bool
	 */
	private function checkBasicRule($rule, $path) {
		$rule = $this->prepareRegexRule($this->encode_url($rule));
		// change @ to \@
		$escaped = strtr($rule, ['@' => '\@']);
		// match result
		if (preg_match('@' . $escaped . '@', $path)) {
			$this->log[] = 'Rule match: Path';
			return true;
		}
		return false;
	}

	/**
	 * Convert robots.txt rules to php regex
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	protected function prepareRegexRule($value) {
		$escape = ['$' => '\$', '?' => '\?', '.' => '\.', '*' => '.*', '[' => '\[', ']' => '\]'];
		foreach ($escape as $search => $replace) {
			$value = str_replace($search, $replace, $value);
		}
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
	 * Strip inline directive prefix
	 *
	 * @param string $rule
	 *
	 * @return string
	 */
	protected function stripInlineDirective($rule) {
		$directive = $this->isInlineDirective($rule);
		if ($directive !== false) {
			$rule = trim(str_ireplace($directive . ':', '', $rule));
		}
		return $rule;
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
			$error_msg   = 'Inline host directive detected. URL not set, result may be inaccurate.';
			$this->log[] = $error_msg;
			trigger_error("robots.txt: $error_msg", E_USER_NOTICE);
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
			$this->log[] = 'Rule match: ' . Directive::HOST . ' directive';
			return true;
		}
		return false;
	}

	/**
	 * Get path
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	private function getPath($url) {
		$url    = trim($url);
		$parsed = $this->parseURL($url);
		if ($parsed !== false) {
			$this->url = $url;
			return $parsed['custom'];
		}
		return $url;
	}

	/**
	 * Check url wrapper
	 *
	 * @param string      $url       - url to check
	 * @param string|null $userAgent - which robot to check for
	 *
	 * @return bool
	 */
	public function isDisallowed($url, $userAgent = null) {
		if ($userAgent !== null) {
			$this->setUserAgent($userAgent);
		}
		$url = $this->encode_url($url);
		return $this->checkRules(Directive::DISALLOW, $this->getPath($url), $this->userAgentMatch);
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
		return $this->log;
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
			if (isset($this->tree[$userAgent][Directive::HOST]) && !empty($this->tree[$userAgent][Directive::HOST])) {
				return $this->tree[$userAgent][Directive::HOST];
			}

			$this->log(sprintf('Failed for find host for %s, checking for * ...', $userAgent));

			if (isset($this->tree['*'][Directive::HOST]) && !empty($this->tree['*'][Directive::HOST])) {
				return $this->tree['*'][Directive::HOST];
			}

			$this->log('Failed for find host for *');

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
			if (isset($this->tree[$userAgent][Directive::SITEMAP]) && !empty($this->tree[$userAgent][Directive::SITEMAP])) {
				return $this->tree[$userAgent][Directive::SITEMAP];
			}

			$this->log(sprintf('Failed for find sitemap for %s, checking for * ...', $userAgent));

			if (isset($this->tree['*'][Directive::SITEMAP]) && !empty($this->tree['*'][Directive::SITEMAP])) {
				return $this->tree['*'][Directive::SITEMAP];
			}

			$this->log('Failed for find sitemap for *');
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
