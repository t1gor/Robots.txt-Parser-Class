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

	class robotstxtparsermachine {

		// кодировка файла по умолчаению
		const DEFAULT_ENCODING 				= 'UTF-8';

		// состояния автомата
		const STATE_ZERO_POINT 				= 'zero-point';
		const STATE_READ_DIRECTIVE			= 'read-directive';
		const STATE_SKIP_SPACE				= 'skip-space';
		const STATE_SKIP_LINE				= 'skip-line';
		const STATE_READ_VALUE				= 'read-value';
		const STATE_ACCUMULATE_USER_AGENT	= 'accumulate-user-agent';

		// директивы
		const DIRECTIVE_ALLOW 				= 'allow';
		const DIRECTIVE_DISALLOW 			= 'disallow';
		const DIRECTIVE_HOST 				= 'host';
		const DIRECTIVE_SITEMAP 			= 'sitemap';
		const DIRECTIVE_USERAGENT 			= 'user-agent';

		// внутренние переменные

		// текущее слово
		protected $current_word = "";

		// текущий проверяемый символ
		protected $current_char = "";

		// номер текущего сивола
		protected $char_index = 0;

		// номер текущей строки
		protected $line_number = 0;

		// текущая и предыдущая директива
		protected $current_directive = "";
		protected $previous_directive = "";

		// текущее и предыдущее значения правила
		protected $previous_value = "";
		protected $current_value = "";

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
			$this->parseRules();
		}

		// сигналы

		/**
		 * Сигнал комментария (#)
		 */
		protected function sharp() {
			return ($this->current_char = '#');
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
			return ($this->current_char = ':');
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
			return ($this->current_char = "\s");
		}

		/**
		 * Сигнал директивы User-agent
		 */
		protected function userAgent() {
			return ($this->current_word == self::DIRECTIVE_USERAGENT);
		}

		/**
		 * TODO: Добавить обработчик на этот сигнал
		 */
		protected function accumulateUserAgent() {
			return ($this->current_directive == self::DIRECTIVE_USERAGENT
				&& $this->previous_directive == self::DIRECTIVE_USERAGENT
			);
		}

		/**
		 * Перейти в состояние
		 *
		 * @param string $stateTo - состояние, к которому надо перейти
		 *
		 * @return void
		 */
		protected function switchState($stateTo = self::STATE_SKIP_LINE) {
			echo "<br/>Переход в состояние ".$stateTo."<br/>";
			$this->state = $stateTo;
		}

		/**
		 * Парсим правила
		 */
		public function parseRules() {
			while ($this->char_index != mb_strlen($this->content)) {
				$this->step();
			}
		}

		/**
		 * Шаг автомата
		 */
		protected function step() {

			echo "Состояние: ".$this->state."<br/>";
			echo "Символ: ".$this->current_char."<br/>";
			echo "Слово: ".$this->current_word."<br/>";
			echo "Текущая директива: ".$this->current_directive." :: предыдущая - ".$this->previous_directive."<br/>";
			echo "Current value: ".$this->current_value." :: previous - ".$this->previous_value."<br/>";
			echo "<br/>";

			switch ($this->state) {

				case self::STATE_ZERO_POINT:
					if ($this->allow() || $this->disallow() || $this->host() || $this->userAgent() || $this->sitemap()) {
						$this->switchState(self::STATE_READ_DIRECTIVE);
					} else {
						$this->current_char = mb_strtolower(mb_substr($this->content, $this->char_index, 1));
						$this->current_word .= $this->current_char;
						$this->char_index++;
					}
				break;

				case self::STATE_READ_DIRECTIVE:
					$this->previous_directive = $this->current_directive;
					$this->current_directive = mb_strtolower(trim($this->current_word));
					$this->current_word = "";

					if ($this->lineSeparator()) {
						$this->switchState(self::STATE_READ_VALUE);
					} else {
						if ($this->space()) {
							$this->switchState(self::STATE_SKIP_SPACE);
						}
					}
				break;

				case self::STATE_SKIP_SPACE:
					$this->char_index++;
				break;

				case self::STATE_SKIP_LINE:
					$this->line_number++;
					$this->switchState(self::STATE_ZERO_POINT);
				break;

				case self::STATE_READ_VALUE:
					if ($this->newLine() || $this->space()) {
						echo $this->current_word; die;
						$this->switchState(self::STATE_ZERO_POINT);
					} else {

					}

					$url = mb_strtolower(trim($this->current_word));

					if ($this->current_directive == self::DIRECTIVE_USERAGENT) {
						$this->rules[$this->userAgent][] = $url;
					}

				break;
			}
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

	}
?>
