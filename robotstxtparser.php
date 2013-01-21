<?php

  /**
	 * Класс для разбора правил файла robots.txt
	 *
	 * @author Igor Timoshenkov (igor.timoshenkov@gmail.com)
	 *
	 * Дополнительные материалы и ссылки:
	 * - https://developers.google.com/webmasters/control-crawl-index/docs/robots_txt
	 * - http://help.yandex.com/webmaster/?id=1113851
	 * - http://socoder.net/index.php?snippet=23824
	 * - http://www.sphider.eu/forum/read.php?3,2740
	 * - http://www.the-art-of-web.com/php/parse-robots/#.UP0C1ZGhM6I
	 */

	class robotstxtparser {

		// cодержимое файла
		public $content = "";

		// кто смотрит
		public $userAgent = "*";

		// набор правил индексации
		public $rules = array();

		// правила для проверки
		const ROBOTS_TXT_DISALLOW 	= 'disallow';
		const ROBOTS_TXT_ALLOW 		= 'allow';
		const ROBOTS_TXT_HOST 		= 'host';
		const ROBOTS_TXT_SITEMAP 	= 'sitemap';

		public function __construct($content, $encoding = '', $userAgent = "*") {
			// преобразование кодировки
			$encoding = !empty($encoding) ? $encoding : mb_detect_encoding($content);
			$this->content = iconv($encoding, 'UTF-8//IGNORE', $content);
			$this->userAgent = $userAgent;
			$this->parseRules();
		}

		/**
		 * Получим набор правил
		 *
		 * @return void
		 */
		public function parseRules()
		{
			$currentAgent = "*";
			$linesArray = explode("\n", $this->content);
			foreach ($linesArray as $line) {

				// skip commented, empty and short lines
				if ((mb_strlen($line) <= 1)) continue;
				if (mb_strpos($line, "#") === 0) continue;

				// get key -> value pairs
				@list($directive, $value) = explode(': ', $line);

				$directive = strtolower(trim($directive));
				$value = rtrim(trim($value), '/');			// remove right slash

				switch ($directive) {

					case 'allow':
						$this->rules[$currentAgent]['allow'][] = self::prepareRegexRule($value);
					break;

					case 'disallow':
						$this->rules[$currentAgent]['disallow'][] = self::prepareRegexRule($value);
					break;

					case 'host':
						$this->rules[$currentAgent]['host'][] = $value;
					break;

					case 'sitemap':
						$this->rules[$currentAgent]['sitemap'][] = $value;
					break;

					case 'user-agent':
						$currentAgent = $value;
					break;

				}
			}
		}

		/**
		 * Проверка правила с указанным значением
		 *
		 * @param string 	$rule      - одна из констант класса
		 * @param mixed 	$value     - значение для сравнения
		 * @param string 	$userAgent - для какого робота (для всех по умолчанию)
		 *
		 * @return bool
		 */
		public function checkRule($rule, $value = '/', $userAgent = '*')
		{
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

		/**
		 * Преобразование robots.txt правила в php регулярку
		 *
		 * @param string $value
		 *
		 * @return string
		 */
		public static function prepareRegexRule($value) {
			$value = str_replace('*', '.*', $value);
			$value = str_replace('.', '\.', $value);
			$value = str_replace('?', '\?', $value);
			$value = str_replace('$', '\$', $value);
			if (mb_strrpos($value, '/') == (mb_strlen($value)-1) ||
				mb_strrpos($value, '=') == (mb_strlen($value)-1) ||
				mb_strrpos($value, '?') == (mb_strlen($value)-1)
			){
				$value .= '.*';
			}
			return $value;
		}
	}

?>
