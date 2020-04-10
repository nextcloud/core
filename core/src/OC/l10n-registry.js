/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// This var is global because it's shared across webpack bundles
window._oc_l10n_registry_translations = window._oc_l10n_registry_translations || {}
window._oc_l10n_registry_plural_functions = window._oc_l10n_registry_plural_functions || {}

/**
 * @param {String} appId the app id
 * @param {Object} translations the translations list
 * @param {Function} pluralFunction the translations list
 */
const register = (appId, translations, pluralFunction) => {
	window._oc_l10n_registry_translations[appId] = translations
	window._oc_l10n_registry_plural_functions[appId] = pluralFunction
}

/**
 * @param {String} appId the app id
 * @param {Object} translations the translations list
 * @param {Function} pluralFunction the translations list
 */
const extend = (appId, translations, pluralFunction) => {
	window._oc_l10n_registry_translations[appId] = Object.assign(
		window._oc_l10n_registry_translations[appId],
		translations
	)
	window._oc_l10n_registry_plural_functions[appId] = pluralFunction
}

/**
 * @param {String} appId the app id
 * @param {Object} translations the translations list
 * @param {Function} pluralFunction the translations list
 */
export const registerAppTranslations = (appId, translations, pluralFunction) => {
	if (!hasAppTranslations(appId)) {
		register(appId, translations, pluralFunction)
	} else {
		extend(appId, translations, pluralFunction)
	}
}

/**
 * @param {String} appId the app id
 */
export const unregisterAppTranslations = appId => {
	delete window._oc_l10n_registry_translations[appId]
	delete window._oc_l10n_registry_plural_functions[appId]
}

/**
 * @param {String} appId the app id
 * @returns {Boolean}
 */
export const hasAppTranslations = appId => {
	return window._oc_l10n_registry_translations[appId] !== undefined
		&& window._oc_l10n_registry_plural_functions[appId] !== undefined
}

/**
 * @param {String} appId the app id
 * @returns {Object}
 */
export const getAppTranslations = appId => {
	return {
		translations: window._oc_l10n_registry_translations[appId] || {},
		pluralFunction: window._oc_l10n_registry_plural_functions[appId],
	}
}
