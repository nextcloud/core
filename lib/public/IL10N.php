<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

/**
 * Public interface of ownCloud for apps to use.
 * L10n interface
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

/**
 * Interface IL10N
 *
 * @since 6.0.0
 */
interface IL10N {
	/**
	 * Translating
	 * @param string $text The text we need a translation for
	 * @param array|string $parameters default:array() Parameters for sprintf
	 * @return string Translation or the same text
	 *
	 * Returns the translation. If no translation is found, $text will be
	 * returned.
	 * @since 6.0.0
	 */
	public function t(string $text, $parameters = []): string;

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
	 * @since 6.0.0
	 *
	 */
	public function n(string $text_singular, string $text_plural, int $count, array $parameters = []): string;

	/**
	 * Localization
	 * @param string $type Type of localization
	 * @param \DateTime|int|string $data parameters for this localization
	 * @param array $options currently supports following options:
	 * 			- 'width': handed into \Punic\Calendar::formatDate as second parameter
	 * @return string|int|false
	 *
	 * Returns the localized data.
	 *
	 * Implemented types:
	 *  - date
	 *    - Creates a date
	 *    - l10n-field: date
	 *    - params: timestamp (int/string)
	 *  - datetime
	 *    - Creates date and time
	 *    - l10n-field: datetime
	 *    - params: timestamp (int/string)
	 *  - time
	 *    - Creates a time
	 *    - l10n-field: time
	 *    - params: timestamp (int/string)
	 * @since 6.0.0 - parameter $options was added in 8.0.0
	 */
	public function l(string $type, $data, array $options = []);


	/**
	 * The code (en, de, ...) of the language that is used for this IL10N object
	 *
	 * @return string language
	 * @since 7.0.0
	 */
	public function getLanguageCode(): string ;

	/**
	 * * The code (en_US, fr_CA, ...) of the locale that is used for this IL10N object
	 *
	 * @return string locale
	 * @since 14.0.0
	 */
	public function getLocaleCode(): string;
}
