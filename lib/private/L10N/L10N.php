<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\L10N;

use OCP\IL10N;
use OCP\L10N\IFactory;
use Punic\Calendar;
use Symfony\Component\Translation\PluralizationRules;

class L10N implements IL10N {

	/** @var IFactory */
	protected $factory;

	/** @var string App of this object */
	protected $app;

	/** @var string Language of this object */
	protected $lang;

	/** @var string Locale of this object */
	protected $locale;

	/** @var string Plural forms (string) */
	private $pluralFormString = 'nplurals=2; plural=(n != 1);';

	/** @var string Plural forms (function) */
	private $pluralFormFunction = null;

	/** @var string[] */
	private $translations = [];

	/**
	 * @param IFactory $factory
	 * @param string $app
	 * @param string $lang
	 * @param string $locale
	 * @param array $files
	 */
	public function __construct(IFactory $factory, $app, $lang, $locale, array $files) {
		$this->factory = $factory;
		$this->app = $app;
		$this->lang = $lang;
		$this->locale = $locale;

		foreach ($files as $languageFile) {
			$this->load($languageFile);
		}
	}

	/**
	 * The code (en, de, ...) of the language that is used for this instance
	 *
	 * @return string language
	 */
	public function getLanguageCode(): string {
		return $this->lang;
	}

	/**
	 * The code (en_US, fr_CA, ...) of the locale that is used for this instance
	 *
	 * @return string locale
	 */
	public function getLocaleCode(): string {
		return $this->locale;
	}

	/**
	 * Translating
	 * @param string $text The text we need a translation for
	 * @param array|string $parameters default:array() Parameters for sprintf
	 * @return string Translation or the same text
	 *
	 * Returns the translation. If no translation is found, $text will be
	 * returned.
	 */
	public function t(string $text, $parameters = []): string {
		if (!\is_array($parameters)) {
			$parameters = [$parameters];
		}

		return (string) new L10NString($this, $text, $parameters);
	}

	/**
	 * Translating
	 * @param string $text_singular the string to translate for exactly one object
	 * @param string $text_plural the string to translate for n objects
	 * @param integer $count Number of objects
	 * @param array $parameters default:array() Parameters for sprintf
	 * @return string Translation or the same text
	 *
	 * Returns the translation. If no translation is found, $text will be
	 * returned. %n will be replaced with the number of objects.
	 *
	 * The correct plural is determined by the plural_forms-function
	 * provided by the po file.
	 *
	 */
	public function n(string $text_singular, string $text_plural, int $count, array $parameters = []): string {
		$identifier = "_${text_singular}_::_${text_plural}_";
		if (isset($this->translations[$identifier])) {
			return (string) new L10NString($this, $identifier, $parameters, $count);
		}

		if ($count === 1) {
			return (string) new L10NString($this, $text_singular, $parameters, $count);
		}

		return (string) new L10NString($this, $text_plural, $parameters, $count);
	}

	/**
	 * Localization
	 * @param string $type Type of localization
	 * @param \DateTime|int|string $data parameters for this localization
	 * @param array $options
	 * @return string|int|false
	 *
	 * Returns the localized data.
	 *
	 * Implemented types:
	 *  - date
	 *    - Creates a date
	 *    - params: timestamp (int/string)
	 *  - datetime
	 *    - Creates date and time
	 *    - params: timestamp (int/string)
	 *  - time
	 *    - Creates a time
	 *    - params: timestamp (int/string)
	 *  - firstday: Returns the first day of the week (0 sunday - 6 saturday)
	 *  - jsdate: Returns the short JS date format
	 */
	public function l(string $type, $data = null, array $options = []) {
		if (null === $this->locale) {
			// Use the language of the instance
			$this->locale = $this->getLanguageCode();
		}
		if ($this->locale === 'sr@latin') {
			$this->locale = 'sr_latn';
		}

		if ($type === 'firstday') {
			return (int) Calendar::getFirstWeekday($this->locale);
		}
		if ($type === 'jsdate') {
			return (string) Calendar::getDateFormat('short', $this->locale);
		}

		$value = new \DateTime();
		if ($data instanceof \DateTime) {
			$value = $data;
		} else if (\is_string($data) && !is_numeric($data)) {
			$data = strtotime($data);
			$value->setTimestamp($data);
		} else if ($data !== null) {
			$data = (int)$data;
			$value->setTimestamp($data);
		}

		$options = array_merge(['width' => 'long'], $options);
		$width = $options['width'];
		switch ($type) {
			case 'date':
				return (string) Calendar::formatDate($value, $width, $this->locale);
			case 'datetime':
				return (string) Calendar::formatDatetime($value, $width, $this->locale);
			case 'time':
				return (string) Calendar::formatTime($value, $width, $this->locale);
			case 'weekdayName':
				return (string) Calendar::getWeekdayName($value, $width, $this->locale);
			default:
				return false;
		}
	}

	/**
	 * Returns an associative array with all translations
	 *
	 * Called by \OC_L10N_String
	 * @return array
	 */
	public function getTranslations(): array {
		return $this->translations;
	}

	/**
	 * Returnsed function accepts the argument $n
	 *
	 * Called by \OC_L10N_String
	 * @return \Closure the plural form function
	 */
	public function getPluralFormFunction(): \Closure {
		if (\is_null($this->pluralFormFunction)) {
			$lang = $this->getLanguageCode();
			$this->pluralFormFunction = function ($n) use ($lang) {
				return PluralizationRules::get($n, $lang);
			};
		}

		return $this->pluralFormFunction;
	}

	/**
	 * @param string $translationFile
	 * @return bool
	 */
	protected function load(string $translationFile): bool {
		$json = json_decode(file_get_contents($translationFile), true);
		if (!\is_array($json)) {
			$jsonError = json_last_error();
			\OC::$server->getLogger()->warning("Failed to load $translationFile - json error code: $jsonError", ['app' => 'l10n']);
			return false;
		}

		if (!empty($json['pluralForm'])) {
			$this->pluralFormString = $json['pluralForm'];
		}
		$this->translations = array_merge($this->translations, $json['translations']);
		return true;
	}
}
