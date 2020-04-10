<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCP\L10N;

use OCP\IUser;

/**
 * @since 8.2.0
 */
interface IFactory {
	/**
	 * Get a language instance
	 *
	 * @param string $app
	 * @param string|null $lang
	 * @param string|null $locale
	 * @return \OCP\IL10N
	 * @since 8.2.0
	 */
	public function get($app, $lang = null, $locale = null);

	/**
	 * Find the best language
	 *
	 * @param string|null $app App id or null for core
	 * @return string language If nothing works it returns 'en'
	 * @since 9.0.0
	 */
	public function findLanguage($app = null);

	/**
	 * @param string|null $lang user language as default locale
	 * @return string locale If nothing works it returns 'en_US'
	 * @since 14.0.0
	 */
	public function findLocale($lang = null);

	/**
	 * find the matching lang from the locale
	 *
	 * @param string $app
	 * @param string $locale
	 * @return null|string
	 * @since 14.0.1
	 */
	public function findLanguageFromLocale(string $app = 'core', string $locale = null);

	/**
	 * Find all available languages for an app
	 *
	 * @param string|null $app App id or null for core
	 * @return string[] an array of available languages
	 * @since 9.0.0
	 */
	public function findAvailableLanguages($app = null);

	/**
	 * @return array an array of available
	 * @since 14.0.0
	 */
	public function findAvailableLocales();

	/**
	 * @param string|null $app App id or null for core
	 * @param string $lang
	 * @return bool
	 * @since 9.0.0
	 */
	public function languageExists($app, $lang);

	/**
	 * @param string $locale
	 * @return bool
	 * @since 14.0.0
	 */
	public function localeExists($locale);

	/**
	 * Creates a function from the plural string
	 *
	 * @param string $string
	 * @return string Unique function name
	 * @since 14.0.0
	 */
	public function createPluralFunction($string);

	/**
	 * iterate through language settings (if provided) in this order:
	 * 1. returns the forced language or:
	 * 2. if applicable, the trunk of 1 (e.g. "fu" instead of "fu_BAR"
	 * 3. returns the user language or:
	 * 4. if applicable, the trunk of 3
	 * 5. returns the system default language or:
	 * 6. if applicable, the trunk of 5
	 * 7+∞. returns 'en'
	 *
	 * Hint: in most cases findLanguage() suits you fine
	 *
	 * @since 14.0.0
	 */
	public function getLanguageIterator(IUser $user = null): ILanguageIterator;
}
