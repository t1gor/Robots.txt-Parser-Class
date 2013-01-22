<?php

	/**
	 * Класс для разбора правил файла robots.txt
	 *
	 * @author Igor Timoshenkov (igor.timoshenkov@gmail.com)
	 *
	 * Граф-схема конечного автомата с описанием состояний и сигналов:
	 * - https://docs.google.com/document/d/1_rNjxpnUUeJG13ap6cnXM6Sx9ZQtd1ngADXnW9SHJSE/edit
	 *
	 * Дополнительные материалы и ссылки:
	 * - https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt
	 * - http://help.yandex.com/webmaster/?id=1113851
	 * - http://socoder.net/index.php?snippet=23824
	 * - http://www.sphider.eu/forum/read.php?3,2740
	 * - http://www.the-art-of-web.com/php/parse-robots/#.UP0C1ZGhM6I
	 */

	class robotstxtparser {

		// кодировка файла по умолчаению
		const DEFAULT_ENCODING 				= 'UTF-8';

		// состояния автомата
		const STATE_ZERO_POINT 				= 'zero-point';
		const STATE_READ_DIRECTIVE			= 'read-directive';
		const STATE_SKIP_SPACE				= 'skip-space';
		const STATE_SKIP_LINE				= 'skip-line';
		const STATE_READ_VALUE				= 'read-value';

		// директивы
		const DIRECTIVE_ALLOW 				= 'allow';
		const DIRECTIVE_DISALLOW 			= 'disallow';
		const DIRECTIVE_HOST 				= 'host';
		const DIRECTIVE_SITEMAP 			= 'sitemap';
		const DIRECTIVE_USERAGENT 			= 'user-agent';

		// внутренние переменные
		public $log_enabled = true;

		// текущее слово
		protected $current_word = "";

		// текущий проверяемый символ
		protected $current_char = "";

		// номер текущего сивола
		protected $char_index = 0;

		// текущая и предыдущая директива
		protected $current_directive = "";
		protected $previous_directive = "";

		// User-Agent
		protected $userAgent = "*";

		// текущее состояние
		public $state = "";

		// содержимое файла robots.txt
		public $content = "";

		// наборы правил
		public $rules = array();

		/**
		 * Конструктор
		 *
		 * @param string $content  - само содержимое файла
		 * @param string $encoding - кодировка файла
		 *
		 * @return void
		 */
		public function __construct($content, $encoding = self::DEFAULT_ENCODING) {
			// преобразование кодировки
			$encoding = !empty($encoding) ? $encoding : mb_detect_encoding($content);
			mb_internal_encoding($encoding);

			// задаем контент
			$this->content = iconv($encoding, 'UTF-8//IGNORE', $content);

			// устанавливаем начальное состояние
			$this->state = self::STATE_ZERO_POINT;

			// парсим правила - первый шаг
			$this->prepareRules();
		}

		// сигналы

		/**
		 * Сигнал комментария (#)
		 */
		protected function sharp() {
			return ($this->current_char == '#');
		}

		/**
		 * Сигнал директивы Allow
		 */
		protected function allow() {
			return ($this->current_word == self::DIRECTIVE_ALLOW);
		}

		/**
		 * Сигнал директивы Disallow
		 */
		protected function disallow() {
			return ($this->current_word == self::DIRECTIVE_DISALLOW);
		}

		/**
		 * Сигнал директивы Host
		 */
		protected function host() {
			return ($this->current_word == self::DIRECTIVE_HOST);
		}

		/**
		 * Сигнал директивы Sitemap
		 */
		protected function sitemap() {
			return ($this->current_word == self::DIRECTIVE_SITEMAP);
		}

		/**
		 * Сигнал разделителя пары ключ : значение
		 */
		protected function lineSeparator() {
			return ($this->current_char == ':');
		}

		/**
		 * Сигнал перехода на новую строку
		 */
		protected function newLine() {
			return ($this->current_char == "\n"
				|| $this->current_word == "\r\n"
				|| $this->current_word == "\n\r"
			);
		}

		/**
		 * Сигнал "пробел"
		 */
		protected function space() {
			return ($this->current_char == "\s");
		}

		/**
		 * Сигнал директивы User-agent
		 */
		protected function userAgent() {
			return ($this->current_word == self::DIRECTIVE_USERAGENT);
		}

		/**
		 * Перейти в состояние
		 *
		 * @param string $stateTo - состояние, к которому надо перейти
		 *
		 * @return void
		 */
		protected function switchState($stateTo = self::STATE_SKIP_LINE) {
			$this->state = $stateTo;
		}

		/**
		 * Парсим правила
		 *
		 * @return void
		 */
		public function prepareRules() {
			while ($this->char_index != mb_strlen($this->content)) {
				$this->step();
			}
		}

		/**
		 * Шаг автомата
		 *
		 * @return void
		 */
		protected function step() {

			switch ($this->state) {

				case self::STATE_ZERO_POINT:
					if ($this->allow() || $this->disallow() || $this->host() || $this->userAgent() || $this->sitemap()) {
						$this->switchState(self::STATE_READ_DIRECTIVE);
					} else {
						$this->increment();
					}
				break;

				case self::STATE_READ_DIRECTIVE:
					$this->previous_directive = $this->current_directive;
					$this->current_directive = mb_strtolower(trim($this->current_word));
					$this->current_word = "";

					$this->increment();

					if ($this->lineSeparator()) {
						$this->current_word = "";
						$this->switchState(self::STATE_READ_VALUE);
					} else {
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
					if ($this->newLine()) {
						if ($this->current_directive == self::DIRECTIVE_USERAGENT) {
							$this->rules[$this->current_word] = array();
							$this->userAgent = $this->current_word;
						} else {
							if ($this->current_directive == self::DIRECTIVE_ALLOW || $this->current_directive == self::DIRECTIVE_DISALLOW) {
								$this->current_word = "/".ltrim($this->current_word, '/');
							}
							$this->rules[$this->userAgent][$this->current_directive][] = self::prepareRegexRule($this->current_word);
						}
						$this->current_word = "";
						$this->switchState(self::STATE_ZERO_POINT);
					} else {
						$this->increment();
					}
				break;
			}
		}

		/**
		 * Преобразование robots.txt правила в php регулярку
		 *
		 * @param string $value
		 *
		 * @return string
		 */
		protected static function prepareRegexRule($value) {
			$value = str_replace('*', '.*', str_replace('.', '\.', str_replace('?', '\?', str_replace('$', '\$', $value))));
			if (mb_strrpos($value, '/') == (mb_strlen($value)-1) ||
				mb_strrpos($value, '=') == (mb_strlen($value)-1) ||
				mb_strrpos($value, '?') == (mb_strlen($value)-1)
			){
				$value .= '.*';
			}
			return $value;
		}

		/**
		 * Общая часть для большинства состояний - пропуск строки и пробела
		 *
		 * @return void
		 */
		protected function skip() {
			if ($this->space()) {
				$this->switchState(self::STATE_SKIP_SPACE);
			}

			if ($this->sharp() || $this->newLine()) {
				$this->switchState(self::STATE_SKIP_LINE);
			}
		}

		/**
		 * Переход к следующему шагу
		 *
		 * @return void
		 */
		protected function increment() {
			$this->current_char = mb_strtolower(mb_substr($this->content, $this->char_index, 1));
			$this->current_word .= $this->current_char;
			$this->current_word = trim($this->current_word);
			$this->char_index++;
		}

		/**
		 * Обертка для проверки url
		 *
		 * @param string $url       - url для проверки
		 * @param string $userAgent - для какого робота проверка
		 *
		 * @return bool
		 */
		public function isAllowed($url, $userAgent = "*") {
			return $this->checkRule(self::DIRECTIVE_ALLOW, $url, $userAgent) && !$this->checkRule(self::DIRECTIVE_DISALLOW, $url, $userAgent);
		}

		/**
		 * Обертка для проверки url
		 *
		 * @param string $url       - url для проверки
		 * @param string $userAgent - для какого робота проверка
		 *
		 * @return bool
		 */
		public function isDisallowed($url, $userAgent = "*") {
			return $this->checkRule(self::DIRECTIVE_DISALLOW, $url, $userAgent);
		}

		/**
		 * Обертка для проверки url
		 *
		 * @param string $rule      - какое правило проверяем
		 * @param string $url       - url для проверки
		 * @param string $userAgent - для какого робота проверка
		 *
		 * @return bool
		 */
		public function checkRule($rule, $value = '/', $userAgent = '*') {
			$result = false;
			// если нет правила или группы паравил по user-agent
			if (!isset($this->rules[$userAgent]) || !isset($this->rules[$userAgent][$rule])) {
				// проверяем категорию "*" - для всех
				return ($userAgent != '*') ? $this->checkRule($rule, $value) : true;
			}
			foreach ($this->rules[$userAgent][$rule] as $robotRule) {
				if (preg_match('@'.$robotRule.'@', $value)) {
					return true;
				}
			}
			return $result;
		}
	}
?>
