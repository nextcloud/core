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

import { subscribe } from '@nextcloud/event-bus'

import { addScript, addStyle } from './legacy-loader'
import {
	ajaxConnectionLostHandler,
	processAjaxError,
	registerXHRForErrorProcessing,
} from './xhr-error'
import Apps from './apps'
import { AppConfig, appConfig } from './appconfig'
import { appSettings } from './appsettings'
import appswebroots from './appswebroots'
import Backbone from './backbone'
import {
	basename,
	dirname,
	encodePath,
	isSamePath,
	joinPaths,
} from '@nextcloud/paths'
import {
	build as buildQueryString,
	parse as parseQueryString,
} from './query-string'
import Config from './config'
import {
	coreApps,
	menuSpeed,
	PERMISSION_ALL,
	PERMISSION_CREATE,
	PERMISSION_DELETE,
	PERMISSION_NONE,
	PERMISSION_READ,
	PERMISSION_SHARE,
	PERMISSION_UPDATE,
	TAG_FAVORITE,
} from './constants'
import ContactsMenu from './contactsmenu'
import { currentUser, getCurrentUser } from './currentuser'
import Dialogs from './dialogs'
import EventSource from './eventsource'
import { get, set } from './get_set'
import { getCapabilities } from './capabilities'
import {
	getHost,
	getHostName,
	getPort,
	getProtocol,
} from './host'
import {
	getToken as getRequestToken,
} from './requesttoken'
import {
	hideMenus,
	registerMenu,
	showMenu,
	unregisterMenu,
} from './menu'
import { isUserAdmin } from './admin'
import L10N, {
	getCanonicalLocale,
	getLanguage,
	getLocale,
} from './l10n'

import {
	generateUrl,
	generateFilePath,
	generateOcsUrl,
	generateRemoteUrl,
	getRootUrl,
	imagePath,
	linkTo,
} from '@nextcloud/router'

import {
	linkToRemoteBase,
} from './routing'
import msg from './msg'
import Notification from './notification'
import PasswordConfirmation from './password-confirmation'
import Plugins from './plugins'
import search from './search'
import { theme } from './theme'
import Util from './util'
import { debug } from './debug'
import { redirect, reload } from './navigation'
import webroot from './webroot'

/** @namespace OC */
export default {
	/*
	 * Constants
	 */
	coreApps,
	menuSpeed,
	PERMISSION_ALL,
	PERMISSION_CREATE,
	PERMISSION_DELETE,
	PERMISSION_NONE,
	PERMISSION_READ,
	PERMISSION_SHARE,
	PERMISSION_UPDATE,
	TAG_FAVORITE,

	/*
	 * Deprecated helpers to be removed
	 */
	/**
	 * Check if a user file is allowed to be handled.
	 * @param {string} file to check
	 * @returns {Boolean}
	 * @deprecated 17.0.0
	 */
	fileIsBlacklisted: file => !!(file.match(Config.blacklist_files_regex)),

	addScript,
	addStyle,
	Apps,
	AppConfig,
	appConfig,
	appSettings,
	appswebroots,
	Backbone,
	ContactsMenu,
	config: Config,
	/**
	 * Currently logged in user or null if none
	 *
	 * @type String
	 * @deprecated use `getCurrentUser` from https://www.npmjs.com/package/@nextcloud/auth
	 */
	currentUser,
	dialogs: Dialogs,
	EventSource,
	/**
	 * Returns the currently logged in user or null if there is no logged in
	 * user (public page mode)
	 *
	 * @since 9.0.0
	 * @deprecated 19.0.0 use `getCurrentUser` from https://www.npmjs.com/package/@nextcloud/auth
	 */
	getCurrentUser,
	isUserAdmin,
	L10N,

	/**
	 * Ajax error handlers
	 * @todo remove from here and keep internally -> requires new tests
	 */
	_ajaxConnectionLostHandler: ajaxConnectionLostHandler,
	_processAjaxError: processAjaxError,
	registerXHRForErrorProcessing,

	/**
	 * Capabilities
	 *
	 * @type {Array}
	 * @deprecated 17.0.0 use OC.getCapabilities() instead
	 */
	_capabilities: getCapabilities(),
	getCapabilities,

	/*
	 * Legacy menu helpers
	 */
	hideMenus,
	registerMenu,
	showMenu,
	unregisterMenu,

	/*
	 * Path helpers
	 */
	/**
	 * @deprecated 18.0.0 use https://www.npmjs.com/package/@nextcloud/paths
	 */
	basename,
	/**
	 * @deprecated 18.0.0 use https://www.npmjs.com/package/@nextcloud/paths
	 */
	encodePath,
	/**
	 * @deprecated 18.0.0 use https://www.npmjs.com/package/@nextcloud/paths
	 */
	dirname,
	/**
	 * @deprecated 18.0.0 use https://www.npmjs.com/package/@nextcloud/paths
	 */
	isSamePath,
	/**
	 * @deprecated 18.0.0 use https://www.npmjs.com/package/@nextcloud/paths
	 */
	joinPaths,

	/**
	 * Host (url) helpers
	 */
	getHost,
	getHostName,
	getPort,
	getProtocol,

	/**
	 * L10n
	 */
	getCanonicalLocale,
	getLocale,
	getLanguage,
	/**
	 * Loads translations for the given app asynchronously.
	 *
	 * @param {String} app app name
	 * @param {Function} callback callback to call after loading
	 * @return {Promise}
	 * @deprecated 17.0.0 use OC.L10N.load instead
	 */
	addTranslations: L10N.load,

	/**
	 * Query string helpers
	 */
	buildQueryString,
	parseQueryString,

	msg,
	Notification,
	PasswordConfirmation,
	Plugins,
	search,
	theme,
	Util,
	debug,
	/**
	 * @deprecated 19.0.0 use `generateFilePath` from https://www.npmjs.com/package/@nextcloud/router
	 */
	filePath: generateFilePath,
	/**
	 * @deprecated 19.0.0 use `generateUrl` from https://www.npmjs.com/package/@nextcloud/router
	 */
	generateUrl,
	/**
	 * @deprecated 19.0.0 use https://lodash.com/docs#get
	 */
	get: get(window),
	/**
	 * @deprecated 19.0.0 use https://lodash.com/docs#set
	 */
	set: set(window),
	/**
	 * @deprecated 19.0.0 use `getRootUrl` from https://www.npmjs.com/package/@nextcloud/router
	 */
	getRootPath: getRootUrl,
	/**
	 * @deprecated 19.0.0 use `imagePath` from https://www.npmjs.com/package/@nextcloud/router
	 */
	imagePath,
	redirect,
	reload,
	requestToken: getRequestToken(),
	/**
	 * @deprecated 19.0.0 use `linkTo` from https://www.npmjs.com/package/@nextcloud/router
	 */
	linkTo,
	/**
	 * @deprecated 19.0.0 use `generateOcsUrl` from https://www.npmjs.com/package/@nextcloud/router
	 */
	linkToOCS: generateOcsUrl,
	/**
	 * @deprecated 19.0.0 use `generateRemoteUrl` from https://www.npmjs.com/package/@nextcloud/router
	 */
	linkToRemote: generateRemoteUrl,
	linkToRemoteBase,
	/**
	 * Relative path to Nextcloud root.
	 * For example: "/nextcloud"
	 *
	 * @type string
	 *
	 * @deprecated 19.0.0 use `getRootUrl` from https://www.npmjs.com/package/@nextcloud/router
	 * @see OC#getRootPath
	 */
	webroot,
}

// Keep the request token prop in sync
subscribe('csrf-token-update', e => {
	OC.requestToken = e.token

	// Logging might help debug (Sentry) issues
	console.info('OC.requestToken changed', e.token)
})
