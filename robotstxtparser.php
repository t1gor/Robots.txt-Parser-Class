<?php

	/**
	 * Class for parsing robots.txt files
	 *
	 * @author Igor Timoshenkov (igor.timoshenkov@gmail.com)
	 *
	 * Logic schem and signals:
	 * @link https://docs.google.com/document/d/1_rNjxpnUUeJG13ap6cnXM6Sx9ZQtd1ngADXnW9SHJSE/edit
	 *
	 * Some useful links and materials:
	 * @link https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt
	 * @link http://help.yandex.com/webmaster/?id=1113851
	 * @link http://socoder.net/index.php?snippet=23824
	 * @link http://www.the-art-of-web.com/php/parse-robots/#.UP0C1ZGhM6I
	 */

	use \InvalidArgumentException;

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

		// language
		const LANG_NO_CONTENT_PASSED = "No content submitted - please check the file that you are using.";

		// internal logs
		public $log_enabled = true;

		// current state
		public $state = "";

		// robots.txt file content
		public $content = "";

		// rules set
		public $rules = array();

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
		 * @return void
		 */
		public function __construct($content, $encoding = self::DEFAULT_ENCODING)
		{
			// checl for empty content
			if (strlen($content) == 0) {
				throw new InvalidArgumentException(self::LANG_NO_CONTENT_PASSED);
			}

			// convert encoding
			$encoding = !empty($encoding) ? $encoding : mb_detect_encoding($content);
			mb_internal_encoding($encoding);

			// set content
			$this->content = iconv($encoding, 'UTF-8//IGNORE', $content);

			// Ensure that there's a newline at the end of the file, otherwise the
			// last line is ignored
			$this->content .= "\n";

			// set default state
			$this->state = self::STATE_ZERO_POINT;

			// parse rools - default state
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
			return ($this->current_char == "\n"
				|| $this->current_word == "\r\n"
				|| $this->current_word == "\n\r"
			);
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
		 * Change state
		 *
		 * @param string $stateTo - state that should be set
		 * @return void
		 */
		protected function switchState($stateTo = self::STATE_SKIP_LINE) {
			$this->state = $stateTo;
		}

		/**
		 * Parse rools
		 *
		 * @return void
		 */
		public function prepareRules()
		{
			$contentLength = mb_strlen($this->content);
			while ($this->char_index <= $contentLength) {
				$this->step();
			}
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
					if ($this->allow()
						|| $this->disallow()
						|| $this->host()
						|| $this->userAgent()
						|| $this->crawlDelay()
						|| $this->sitemap()
					) {
						$this->switchState(self::STATE_READ_DIRECTIVE);
					}
					elseif ($this->newLine()) {
						// unknown directive - skip it
						$this->current_word = "";
						$this->increment();
					}
					else {
						$this->increment();
					}
				break;

				case self::STATE_READ_DIRECTIVE:
					$this->previous_directive = $this->current_directive;
					$this->current_directive = mb_strtolower(trim($this->current_word));
					$this->current_word = "";

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
					}
				break;

				case self::STATE_SKIP_SPACE:
					$this->char_index++;
					$this->current_word = mb_substr($this->current_word, -1);
				break;

				case self::STATE_SKIP_LINE:
					$this->char_index++;
					$this->switchState(self::STATE_ZERO_POINT);
				break;

				case self::STATE_READ_VALUE:
					if ($this->newLine())
					{
						if ($this->current_directive == self::DIRECTIVE_USERAGENT)
						{
							if (empty($this->rules[$this->current_word])) {
								$this->rules[$this->current_word] = array();
							}
							$this->userAgent = $this->current_word;
						}
						elseif ($this->current_directive == self::DIRECTIVE_CRAWL_DELAY)
						{
							$this->rules[$this->userAgent][$this->current_directive] = $this->current_word;
						}
						elseif ($this->current_directive == self::DIRECTIVE_SITEMAP) {
							$this->rules[$this->userAgent][$this->current_directive][] = $this->current_word;
						}
						else {
							if ($this->current_directive == self::DIRECTIVE_ALLOW
								|| $this->current_directive == self::DIRECTIVE_DISALLOW
							) {
								if (!empty($this->current_word)) {
									$this->current_word = "/".ltrim($this->current_word, '/');
								}
							}
							if (!empty($this->current_word)) {
								$this->rules[$this->userAgent][$this->current_directive][] = self::prepareRegexRule($this->current_word);
							}
						}
						$this->current_word = "";
						$this->switchState(self::STATE_ZERO_POINT);
					}
					else {
						$this->increment();
					}
				break;
			}
		}

		/**
		 * Convert robots.txt rool to php regex
		 *
		 * @param string $value
		 * @return string
		 */
		protected static function prepareRegexRule($value)
		{
			$value = str_replace('*', '.*', str_replace('.', '\.', str_replace('?', '\?', str_replace('$', '\$', $value))));
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
			$this->current_char = mb_strtolower(mb_substr($this->content, $this->char_index, 1));
			$this->current_word .= $this->current_char;
			$this->current_word = trim($this->current_word);
			$this->char_index++;
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
			return $this->checkRule(self::DIRECTIVE_ALLOW, $url, $userAgent)
				&& !$this->checkRule(self::DIRECTIVE_DISALLOW, $url, $userAgent);
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
			return $this->checkRule(self::DIRECTIVE_DISALLOW, $url, $userAgent);
		}

		/**
		 * Check url rules
		 *
		 * @param  string $rule      - which rule to check
		 * @param  string $url       - url to check
		 * @param  string $userAgent - which robot to check for
		 * @return bool
		 */
		public function checkRule($rule, $value = '/', $userAgent = '*')
		{
			$result = false;

			// if there is no rule or a set of rules for user-agent
			if (!isset($this->rules[$userAgent]) || !isset($this->rules[$userAgent][$rule]))
			{
				// check 'For all' category - '*'
				return ($userAgent != '*') ? $this->checkRule($rule, $value) : false;
			}

			foreach ($this->rules[$userAgent][$rule] as $robotRule)
			{
				if (preg_match('@'.$robotRule.'@', $value)) {
					return true;
				}
			}

			return $result;
		}

		/**
		 * Sitemaps check wrapper
		 *
		 * @param  string $userAgent - which robot to check for
		 * @return mixed
		 */
		public function getSitemaps($userAgent = '*')
		{
			// if there is not rule or a set of rules for UserAgent
			if (!isset($this->rules[$userAgent]) || !isset($this->rules[$userAgent][self::DIRECTIVE_SITEMAP]))
			{
				// check for all
				return ($userAgent != '*') ? $this->getSitemaps() : false;
			}

			return $this->rules[$userAgent][self::DIRECTIVE_SITEMAP];
		}
	}
?>
