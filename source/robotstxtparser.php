<?php

	/**
	 * Class for parsing robots.txt files
	 *
	 * @author Igor Timoshenkov (igor.timoshenkov@gmail.com)
	 *
	 * Logic schema and signals:
	 * @link https://docs.google.com/document/d/1_rNjxpnUUeJG13ap6cnXM6Sx9ZQtd1ngADXnW9SHJSE/edit
	 *
	 * Some useful links and materials:
	 * @link https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt
	 * @link http://help.yandex.com/webmaster/?id=1113851
	 * @link http://socoder.net/index.php?snippet=23824
	 * @link http://www.the-art-of-web.com/php/parse-robots/#.UP0C1ZGhM6I
	 */
class RobotsTxtParser
{
		// default encoding
		const DEFAULT_ENCODING = 'UTF-8';

		// states
		const STATE_ZERO_POINT     = 'zero-point';
		const STATE_READ_DIRECTIVE = 'read-directive';
		const STATE_SKIP_SPACE     = 'skip-space';
		const STATE_SKIP_LINE      = 'skip-line';
		const STATE_READ_VALUE     = 'read-value';

		// directives
		const DIRECTIVE_ALLOW       = 'allow';
		const DIRECTIVE_DISALLOW    = 'disallow';
		const DIRECTIVE_HOST        = 'host';
		const DIRECTIVE_SITEMAP     = 'sitemap';
		const DIRECTIVE_USERAGENT   = 'user-agent';
		const DIRECTIVE_CRAWL_DELAY = 'crawl-delay';
		const DIRECTIVE_CACHE_DELAY = 'cache-delay';
		const DIRECTIVE_CLEAN_PARAM = 'clean-param';

		// language
		const LANG_NO_CONTENT_PASSED = "No content submitted - please check the file that you are using.";

		// rule validation mode
		private $validationMode = false;

		// current state
		private $state = "";
		
	// url
	private $url = null;

		// robots.txt file content
		private $content = "";

		// rules set
		private $rules = array();
		
		// clean param set
		protected $cleanparam = array();

		// sitemap set
		protected $sitemap = array();

		// host set
		protected $host = array();

		// robots.txt http status code
		protected $httpStatusCode = 200;

		// internally used variables
		protected $current_word = "";
		protected $current_char = "";
		protected $char_index = 0;
		protected $current_directive = "";
		protected $previous_directive = "";
		protected $userAgent = "*";
		protected $userAgent_groups = array();

		/**
		 * @param  string $content  - file content
		 * @param  string $encoding - encoding
		 * @throws InvalidArgumentException
		 */
		public function __construct($content = '', $encoding = self::DEFAULT_ENCODING)
		{
			// convert encoding
			$encoding = !empty($encoding) ? $encoding : mb_detect_encoding($content);
			mb_internal_encoding($encoding);

			// set content
			$this->content = iconv($encoding, 'UTF-8//IGNORE', $content);

			// Ensure that there's a newline at the end of the file, otherwise the
			// last line is ignored
			$this->content .= PHP_EOL;

			// set default state
			$this->state = self::STATE_ZERO_POINT;

			// parse rules - default state
			$this->prepareRules();
		}

		// signals

		/**
		 * Comment signal (#)
		 */
		protected function sharp() {
			return ($this->current_char == '#');
		}

		/**
		 * Allow directive signal
		 */
		protected function allow() {
			return ($this->current_word == self::DIRECTIVE_ALLOW);
		}

		/**
		 * Disallow directive signal
		 */
		protected function disallow() {
			return ($this->current_word == self::DIRECTIVE_DISALLOW);
		}

		/**
		 * Host directive signal
		 */
		protected function host() {
			return ($this->current_word == self::DIRECTIVE_HOST);
		}

		/**
		 * Sitemap directive signal
		 */
		protected function sitemap() {
			return ($this->current_word == self::DIRECTIVE_SITEMAP);
		}

		/**
		 * Key : value pair separator signal
		 */
		protected function lineSeparator() {
			return ($this->current_char == ':');
		}

		/**
		 * Move to new line signal
		 */
		protected function newLine()
		{
            return in_array(PHP_EOL, array(
                $this->current_char,
                $this->current_word
            ));
		}

		/**
		 * "Space" signal
		 */
		protected function space() {
			return ($this->current_char == "\s");
		}

		/**
		 * User-agent directive signal
		 */
		protected function userAgent() {
			return ($this->current_word == self::DIRECTIVE_USERAGENT);
		}

		/**
		 * Crawl-Delay directive signal
		 */
		protected function crawlDelay() {
			return ($this->current_word == self::DIRECTIVE_CRAWL_DELAY);
		}
		
		/**
		 * Cache-Delay directive signal
		 */
		protected function cacheDelay()
		{
			return ($this->current_word == self::DIRECTIVE_CACHE_DELAY);
		}

		/**
		 * Change state
		 *
		 * @param string $stateTo - state that should be set
		 * @return void
		 */
		protected function switchState($stateTo = self::STATE_SKIP_LINE) {
			$this->state = $stateTo;
		}

	/**
	 * Directive array
	 *
	 * @return array
	 */
	protected function directiveArray()
	{
		return array(
			self::DIRECTIVE_ALLOW,
			self::DIRECTIVE_DISALLOW,
			self::DIRECTIVE_HOST,
			self::DIRECTIVE_USERAGENT,
			self::DIRECTIVE_SITEMAP,
			self::DIRECTIVE_CRAWL_DELAY,
			self::DIRECTIVE_CACHE_DELAY,
			self::DIRECTIVE_CLEAN_PARAM
		);
	}

		/**
		 * Parse rules
		 *
		 * @return void
		 */
		public function prepareRules()
		{
			$contentLength = mb_strlen($this->content);
			while ($this->char_index <= $contentLength) {
				$this->step();
			}

			foreach ($this->rules as $userAgent => $directive) {
				foreach ($directive as $directiveName => $directiveValue) {
					if (is_array($directiveValue)) {
						$this->rules[$userAgent][$directiveName] = array_values(array_unique($directiveValue));
					}
				}
			}
		}

	/**
	 * Check if we should switch
	 *
	 * @return bool
	 */
	protected function shouldSwitchToZeroPoint()
	{
		return in_array(mb_strtolower($this->current_word), $this->directiveArray(), true);
	}

		/**
		 * Process state ZERO_POINT
		 *
		 * @return RobotsTxtParser
		 */
		protected function zeroPoint()
		{
			if ($this->shouldSwitchToZeroPoint()) {
				$this->switchState(self::STATE_READ_DIRECTIVE);
			}
			// unknown directive - skip it
			elseif ($this->newLine()) {
				$this->current_word = "";
				$this->increment();
			}
			else {
				$this->increment();
			}
			return $this;
		}

		/**
		 * Read directive
         *
		 * @return RobotsTxtParser
		 */
		protected function readDirective()
		{
			$this->previous_directive = $this->current_directive;
			$this->current_directive = mb_strtolower(trim($this->current_word));

			$this->increment();

			if ($this->lineSeparator())
			{
				$this->current_word = "";
				$this->switchState(self::STATE_READ_VALUE);
			}
			else {
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
		 * Skip space
         *
		 * @return RobotsTxtParser
		 */
		protected function skipSpace()
		{
			$this->char_index++;
			$this->current_word = mb_substr($this->current_word, -1);
			return $this;
		}

		/**
		 * Skip line
		 *
		 * @return RobotsTxtParser
		 */
		protected function skipLine()
		{
			$this->char_index++;
			$this->switchState(self::STATE_ZERO_POINT);
			return $this;
		}

	/**
	 * Read value
	 *
	 * @return RobotsTxtParser
	 */
	protected function readValue()
	{
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
	 * Add value to directive based on the directive type
	 *
	 * @return void
	 */
	private function addValueToDirective()
	{
		switch ($this->current_directive) {
			case self::DIRECTIVE_USERAGENT:
				$this->setUserAgent(mb_strtolower($this->current_word));
				break;
			case self::DIRECTIVE_CACHE_DELAY:
			case self::DIRECTIVE_CRAWL_DELAY:
				$this->convert("trim");
				$this->convert("floatval");
				$this->addGroupMember(false);
				break;
			case self::DIRECTIVE_HOST:
			case self::DIRECTIVE_SITEMAP:
				$this->convert("trim");
				$this->addNonMember();
				break;
			case self::DIRECTIVE_CLEAN_PARAM:
				$this->convert("trim");
				$this->addCleanParam();
				break;
			case self::DIRECTIVE_ALLOW:
			case self::DIRECTIVE_DISALLOW:
				$this->convert("trim");
				if (empty($this->current_word)) {
					break;
				}
				$this->convert("self::prepareRegexRule");
				$this->addGroupMember();
				break;
		}
		// clean-up
		$this->current_word = "";
		$this->switchState(self::STATE_ZERO_POINT);
	}
	
	/**
	 * Set the HTTP status code
	 *
	 * @param int $code
	 * @throws \DomainException
	 */
	public function setHttpStatusCode($code)
	{
		$code = intval($code);
		if (isset($code) && is_int($code) && $code >= 100 && $code <= 599) {
			$this->httpStatusCode = $code;
		} else {
			throw new \DomainException('Invalid HTTP status code');
		}
	}

        /**
         * Set current user agent
         *
         * @param string $newAgent
         */
        private function setUserAgent($newAgent = "*")
        {
            $this->userAgent = trim(mb_strtolower($newAgent));

            // create empty array if not there yet
            if (empty($this->rules[$this->userAgent])) {
                $this->rules[$this->userAgent] = array();
            }
        }

        /**
	 *  Determine the correct user agent group
	 *
	 * @param string $userAgent
	 * @return string
	 */
	protected function determineUserAgentGroup($userAgent = '*')
	{
		if (isset($userAgent) && is_string($userAgent)) {
			$userAgent = mb_strtolower($userAgent);
		} else {
			throw new \DomainException('UserAgent need to be a string');
		}
		foreach ($this->explodeUserAgent($userAgent) as $group) {
			if (isset($this->rules[$group])) {
				return $group;
			}
		}
		return '*';
	}

	/**
	 *  Parses all possible userAgent groups to an array
	 *
	 * @param string $userAgent
	 * @return array
	 */
	private function explodeUserAgent($userAgent = '*')
	{
		$this->userAgent_groups = array($userAgent);
		$this->userAgent_groups[] = $this->stripUserAgentVersion($userAgent);
		$delimiter = '-';
		while (strpos(end($this->userAgent_groups), $delimiter) !== false) {
			$current = end($this->userAgent_groups);
			$this->userAgent_groups[] = substr($current, 0, strrpos($current, $delimiter));
		}
		$this->userAgent_groups[] = '*';
		$this->userAgent_groups = array_unique($this->userAgent_groups);
		return $this->userAgent_groups;
	}

	/**
	 *  Removes the userAgent version
	 *
	 * @param string $userAgent
	 * @return string
	 */
	private function stripUserAgentVersion($userAgent)
	{
		$delimiter = '/';
		if (strpos($userAgent, $delimiter) !== false) {
			$stripped = explode($delimiter, $userAgent, 2)[0];
			return $stripped;
		}
		return $userAgent;
	}

	/**
	 * Add group-member rule
	 *
	 * @param bool $append
	 * @return void
	 */
	private function addGroupMember($append = true)
	{
		if ($append === true) {
			$this->rules[$this->userAgent][$this->current_directive][] = $this->current_word;
		} else {
			$this->rules[$this->userAgent][$this->current_directive] = $this->current_word;
		}
	}

	/**
	 * Add non-group record
	 *
	 * @return void
	 */
	private function addNonMember()
	{
		switch ($this->current_directive) {
			case self::DIRECTIVE_HOST:
				$this->host[] = $this->current_word;
				break;
			case self::DIRECTIVE_SITEMAP:
				$this->sitemap[] = $this->current_word;
				break;
		}
	}

	/**
	 * Add Clean-Param record
	 *
	 * @return void
	 */
	private function addCleanParam()
	{
		$array = explode(' ', $this->current_word, 2);
		$path = isset($array[1]) ? trim($array[1]) : '/*';
		$parameters = explode('&', $array[0]);
		foreach ($parameters as $param) {
			$param = trim($param);
			$this->cleanparam[$param][] = $path;
			$this->cleanparam[$param] = array_unique($this->cleanparam[$param]);
		}
	}

	/**
	 * Convert wrapper
	 *
	 * @param string $convert
	 * @return void
	 */
	private function convert($convert)
	{
		$this->current_word = call_user_func($convert, $this->current_word);
	}

		/**
		 * Machine step
		 *
		 * @return void
		 */
		protected function step()
		{
			switch ($this->state)
			{
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
		 * Convert robots.txt rules to php regex
		 *
		 * @link https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt
		 * @param string $value
		 * @return string
		 */
		protected function prepareRegexRule($value)
		{
			$value = str_replace('$', '\$', $value);
			$value = str_replace('?', '\?', $value);
			$value = str_replace('.', '\.', $value);
			$value = str_replace('*', '.*', $value);

			if (mb_strlen($value) > 2 && mb_substr($value, -2) == '\$') {
				$value = substr($value, 0, -2).'$';
			}

			if (mb_strrpos($value, '/') == (mb_strlen($value)-1) ||
				mb_strrpos($value, '=') == (mb_strlen($value)-1) ||
				mb_strrpos($value, '?') == (mb_strlen($value)-1)
			) {
				$value .= '.*';
			}
			return $value;
		}

		/**
		 * Common part for the most of the states - skip line and space
		 *
		 * @return void
		 */
		protected function skip()
		{
			if ($this->space()) {
				$this->switchState(self::STATE_SKIP_SPACE);
			}

			if ($this->sharp() || $this->newLine()) {
				$this->switchState(self::STATE_SKIP_LINE);
			}
		}

	/**
	 * Move to the following step
	 *
	 * @return void
	 */
	protected function increment()
	{
		$this->current_char = mb_substr($this->content, $this->char_index, 1);
		$this->current_word .= $this->current_char;
		$this->current_word = ltrim($this->current_word);
		$this->char_index++;
	}

	/**
	 * Check if the rule contains a inline directive
	 *
	 * @param  string $rule
	 * @return string|false
	 */
	protected function ruleIsInlineDirective($rule)
	{
		$rule = mb_strtolower($rule);
		foreach ($this->directiveArray() as $directive) {
			if (0 === strpos($rule, $directive . ':')) {
				return $directive;
			}
		}
		return false;
	}

		/**
		 * Check the rule parsing credibility
		 *
		 * @param  string $url       - url to check
		 * @param  string $userAgent - which robot to check for
		 * @throws \DomainException
		 */
		protected function checkEqualRules($url, $userAgent)
		{
			if ($this->validationMode === false) {
				return;
			}
			$allow = $this->checkRule(self::DIRECTIVE_ALLOW, $url, $userAgent);
			$disallow = $this->checkRule(self::DIRECTIVE_DISALLOW, $url, $userAgent);

			if ($allow === $disallow) {
				throw new \DomainException('Unable to check rules');
			}
		}

	/**
	 * Parse URL
	 *
	 * @param  string $url
	 * @return array|false
	 */
	protected function parse_url($url)
	{
		$parsed = parse_url($url);
		if ($parsed === false) {
			return false;
		}
		if (!isset($parsed['scheme']) || !$this->isValidScheme($parsed['scheme'])) {
			return false;
		}
		if (!isset($parsed['host']) || !$this->isValidHostName($parsed['host'])) {
			return false;
		}
		if (!isset($parsed['port'])) {
			$parsed['port'] = getservbyname($parsed['scheme'], 'tcp');
			if (!is_int($parsed['port'])) {
				return false;
			}
		}
		return $parsed;
	}

	/**
	 * Validate URL scheme
	 *
	 * @param  string $scheme
	 * @return bool
	 */
	protected function isValidScheme($scheme)
	{
		return in_array($scheme, array('http', 'https'));
	}
	
	/**
	 * Validate host name
	 *
	 * @param  string $host
	 * @return bool
	 */
	protected function  isValidHostName($host)
	{
		return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $host) //valid chars check
			&& preg_match("/^.{1,253}$/", $host) //overall length check
			&& preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $host) //length of each label
			&& !filter_var($host, FILTER_VALIDATE_IP)); //is not an IP address
	}
	
	/**
	 * Enter validation mode
	 *
	 * @param bool $bool
	 */
	public function enterValidationMode($bool = true)
	{
		if (is_bool($bool)) {
			$this->validationMode = $bool;
		}
	}

		/**
		 * Check url wrapper
		 *
		 * @param  string $url       - url to check
		 * @param  string $userAgent - which robot to check for
		 * @return bool
		 */
		public function isAllowed($url, $userAgent = "*")
		{
			$userAgent = $this->determineUserAgentGroup($userAgent);
			$this->checkEqualRules($url, $userAgent);
			return $this->checkRule(self::DIRECTIVE_ALLOW, $url, $userAgent);
		}

		/**
		 * Check url wrapper
		 *
		 * @param  string $url       - url to check
		 * @param  string $userAgent - which robot to check for
		 * @return bool
		 */
		public function isDisallowed($url, $userAgent = "*")
		{
			$userAgent = $this->determineUserAgentGroup($userAgent);
			$this->checkEqualRules($url, $userAgent);
			return $this->checkRule(self::DIRECTIVE_DISALLOW, $url, $userAgent);
		}

		/**
		 * Check url rules
		 *
		 * @param  string $rule        - which rule to check
		 * @param  string $value       - url to check
		 * @param  string $userAgent   - which robot to check for
		 * @internal param string $url - url to check
		 * @return bool
		 */
		public function checkRule($rule, $value = '/', $userAgent = '*')
		{
			$userAgent = $this->determineUserAgentGroup($userAgent);
			$result = ($rule === self::DIRECTIVE_ALLOW);

			// check the http status code
			if ($this->httpStatusCode >= 500 && $this->httpStatusCode <= 599) {
				return ($rule === self::DIRECTIVE_DISALLOW);
			}

			// if rules are empty - allowed by default
			if (empty($this->rules)) {
				return ($rule === self::DIRECTIVE_ALLOW);
			}

			// if there is no rule or a set of rules for user-agent
			if (!isset($this->rules[$userAgent]) || (!isset($this->rules[$userAgent][self::DIRECTIVE_ALLOW]) && !isset($this->rules[$userAgent][self::DIRECTIVE_DISALLOW]))) {
				// check 'For all' category - '*'
				return ($userAgent != '*') ? $this->checkRule($rule, $value) : ($rule === self::DIRECTIVE_ALLOW);
			}

			$directives = array(self::DIRECTIVE_DISALLOW, self::DIRECTIVE_ALLOW);
			foreach ($directives as $directive) {
				if (isset($this->rules[$userAgent][$directive])) {
					foreach ($this->rules[$userAgent][$directive] as $robotRule) {
					$inline = $this->ruleIsInlineDirective($robotRule);
					switch ($inline) {
						case self::DIRECTIVE_CLEAN_PARAM:
							// TODO: Add support for inline directive Clean-param
							$result = ($rule === $directive);
							break;
						case self::DIRECTIVE_HOST;
							if (!isset($this->url)) {
								trigger_error('Unable to check Host directive. Destination URL not set. The result may be inaccurate.', E_USER_NOTICE);
								continue;
							}
							$url = $this->parse_url($this->url);
							$host = trim(str_replace(self::DIRECTIVE_HOST . ':', '', mb_strtolower($robotRule)));
							if (in_array($host, array(
								$this->prepareRegexRule($url['host']),
								$this->prepareRegexRule($url['scheme'] . '://' . $url['host']),
								$this->prepareRegexRule($url['host'] . ':' . $url['port']),
								$this->prepareRegexRule($url['scheme'] . '://' . $url['host'] . ':' . $url['port'])
							))) {
								$result = ($rule === $directive);
							}
							break;
						default:
							// change @ to \@
							$escaped = strtr($robotRule, array("@" => "\@"));
							// match result
							if (preg_match('@' . $escaped . '@', $value)) {
								if (strpos($escaped, '$') !== false) {
									if (mb_strlen($escaped) - 1 == mb_strlen($value)) {
										$result = ($rule === $directive);
									}
								} else {
									$result = ($rule === $directive);
								}
							}
					}
					}
				}
			}
			return $result;
		}

	/**
	 * Set URL wrapper
	 *
	 * @param  string $url
	 * @throws \DomainException
	 * @return void
	 */
	public function setURL($url)
	{
		$parsed = $this->parse_url($url);
		if ($parsed === false) {
			throw new \DomainException('Invalid URL');
		}
		$this->url = $url;
	}

	/**
	 * Get host wrapper
	 *
	 * @return string|null
	 */
	public function getHost()
	{
		foreach ($this->host as $value) {
			$parsed = parse_url($value);
			if ($parsed === false) {
				continue;
			}
			// Is valid domain
			$host = isset($parsed['host']) ? $parsed['host'] : $parsed['path'];
			if (!$this->isValidHostName($host)) {
				continue;
			}
			if (isset($parsed['scheme']) && !$this->isValidScheme($parsed['scheme'])) {
				continue;
			}
			$scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
			$port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
			if ($value == $scheme . $host . $port) {
				return $value;
			}
		}
		return null;
	}

		/**
		 * Get sitemaps wrapper
		 *
		 * @return array
		 */
		public function getSitemaps()
		{
			$this->sitemap = array_unique($this->sitemap);
			return $this->sitemap;
		}

	/**
	 * Get delay
	 *
	 * @param  string $userAgent - which robot to check for
	 * @param  string $type - in case of non-standard directive
	 * @return int|float
	 */
	public function getDelay($userAgent = '*', $type = 'crawl-delay')
	{
		$userAgent = $this->determineUserAgentGroup($userAgent);
		$type = mb_strtolower($type);
		switch ($type) {
			case 'cache':
			case 'cache-delay':
				$directive = self::DIRECTIVE_CACHE_DELAY;
				break;
			case 'crawl':
			case 'crawl-delay':
			default:
				$directive = self::DIRECTIVE_CRAWL_DELAY;
		}
		return isset($this->rules[$userAgent][$directive])
			? $this->rules[$userAgent][$directive]
			: 0;
	}

        /**
         * Get rules based on user agent
         *
         * @param string|null $userAgent
         * @return array
         */
        public function getRules($userAgent = null)
        {
            // return all rules
            if (is_null($userAgent)) {
                return $this->rules;
            }
            $userAgent = $this->determineUserAgentGroup($userAgent);
            if (isset($this->rules[$userAgent])) {
                return $this->rules[$userAgent];
            }
            else {
                return array();
            }
        }
        
        /**
	 * Get Clean-Param
	 *
	 * @return array
	 */
	public function getCleanParam()
	{
		return $this->cleanparam;
	}

        /**
         * Get the robots.txt content
         *
         * @return string
         */
        public function getContent()
        {
            return $this->content;
        }
}
