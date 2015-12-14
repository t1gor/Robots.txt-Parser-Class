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
	class RobotsTxtParser {

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

		// current state
		private $state = "";

		// robots.txt file content
		private $content = "";

		// rules set
		private $rules = array();
		
		// sitemaps set
		private $sitemaps = array();

		// internally used variables
		protected $current_word = "";
		protected $current_char = "";
		protected $char_index = 0;
		protected $current_directive = "";
		protected $previous_directive = "";
		protected $userAgent = "*";

		/**
		 * @param  string $content  - file content
		 * @param  string $encoding - encoding
		 * @throws InvalidArgumentException
		 * @return RobotsTxtParser
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
		 * @return bool
		 */
		protected function shouldSwitchToZeroPoint()
		{
			return in_array(mb_strtolower($this->current_word), array(
				self::DIRECTIVE_ALLOW,
				self::DIRECTIVE_DISALLOW,
				self::DIRECTIVE_HOST,
				self::DIRECTIVE_USERAGENT,
				self::DIRECTIVE_SITEMAP,
				self::DIRECTIVE_CRAWL_DELAY,
				self::DIRECTIVE_CACHE_DELAY,
				self::DIRECTIVE_CLEAN_PARAM
			), true);
		}

		/**
		 * Process state ZERO_POINT
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
		 * @return RobotsTxtParser
		 */
		protected function readValue()
		{
			if ($this->newLine()) {
				$this->addValueToDirective();
			}
			elseif ($this->sharp()) {
				$this->current_word = mb_substr($this->current_word, 0, -1);
				$this->addValueToDirective();
			}
			else {
				$this->increment();
			}
			return $this;
		}

        /**
         * Add value to directive based on the directive type
         */
		private function addValueToDirective()
        {
            switch ($this->current_directive)
            {
                case self::DIRECTIVE_USERAGENT:
                    $this->setUserAgent($this->current_word);
                    break;

                case self::DIRECTIVE_CRAWL_DELAY:
                    $this->addRule("floatval", false);
                    break;

		case self::DIRECTIVE_CACHE_DELAY:
			$this->addRule("floatval", false);
			break;

                case self::DIRECTIVE_SITEMAP:
                	$this->addSitemap();
                	break;
                	
                case self::DIRECTIVE_CLEAN_PARAM:
                    $this->addRule();
                    break;

                case self::DIRECTIVE_HOST:
                    $this->addRule("trim", false);
                    break;

                case self::DIRECTIVE_ALLOW:
                case self::DIRECTIVE_DISALLOW:
                    if (empty($this->current_word)) {
                        break;
                    }
                    $this->addRule("self::prepareRegexRule");
                    break;
            }

            // clean-up
            $this->current_word = "";
            $this->switchState(self::STATE_ZERO_POINT);
        }

        /**
         * Set current user agent
         * @param string $newAgent
         */
        private function setUserAgent($newAgent = "*")
        {
            $this->userAgent = $newAgent;

            // create empty array if not there yet
            if (empty($this->rules[$this->userAgent])) {
                $this->rules[$this->userAgent] = array();
            }
        }

        /**
         * Prepare rule value and set the one
         * @param callable $convert
         * @param bool     $append
         * @return void
         */
        private function addRule($convert = null, $append = true)
        {
            // convert value
            $value = (!is_null($convert))
                ? call_user_func($convert, $this->current_word)
                : $this->current_word;

            // set to rules
            if ($append === true) {
                $this->rules[$this->userAgent][$this->current_directive][] = $value;
            }
            else {
                $this->rules[$this->userAgent][$this->current_directive] = $value;
            }
        }
        
        /**
         * Add sitemap wrapper
         * 
         * @return void
         */
         private function addSitemap()
         {
         	$this->sitemaps[] = $this->current_word;
         	$this->sitemaps = array_unique($this->sitemaps);
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
            $value = "/" . ltrim($value, '/');
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
			$this->current_word = trim($this->current_word);
			$this->char_index++;
		}

		/**
		 * @param  string $url       - url to check
		 * @param  string $userAgent - which robot to check for
		 * @throws \DomainException
		 */
		protected function checkEqualRules($url, $userAgent)
		{
			$allow = $this->checkRule(self::DIRECTIVE_ALLOW, $url, $userAgent);
			$disallow = $this->checkRule(self::DIRECTIVE_DISALLOW, $url, $userAgent);

			if ($allow === $disallow) {
				throw new \DomainException('Unable to check rules');
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
			$result = ($rule === self::DIRECTIVE_ALLOW);

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
						// change @ for \@
						$escaped = strtr($robotRule, array("@" => "\@"));

						// match result
						if (preg_match('@' . $escaped . '@', $value)) {
							$result = ($rule === $directive);
						}
					}
				}
			}
			return $result;
		}
		
		/**
		 * Get Cache-Delay
		 *
		 * @param  string $userAgent - which robot to check for
		 * @return float
		 */
		public function getCacheDelay($userAgent = "*")
		{
			$userAgent = mb_strtolower($userAgent);
			return isset($this->rules[$userAgent][self::DIRECTIVE_CACHE_DELAY])
				? $this->rules[$userAgent][self::DIRECTIVE_CACHE_DELAY]
				: 0;
		}

		/**
		 * Get sitemaps wrapper
		 *
		 * @return array
		 */
		public function getSitemaps()
		{
			return $this->sitemaps;
		}
		
		/**
		 * Get Crawl-Delay
		 *
		 * @param  string $userAgent - which robot to check for
		 * @return float
		 */
		public function getCrawlDelay($userAgent = '*')
		{
			$userAgent = mb_strtolower($userAgent);
			return isset($this->rules[$userAgent][self::DIRECTIVE_CRAWL_DELAY])
				? $this->rules[$userAgent][self::DIRECTIVE_CRAWL_DELAY]
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
            elseif (isset($this->rules[$userAgent])) {
                return $this->rules[$userAgent];
            }
            else {
                return array();
            }
        }

        /**
         * @return string
         */
        public function getContent()
        {
            return $this->content;
        }
	}
