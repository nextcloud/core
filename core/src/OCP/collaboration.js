/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import escapeHTML from 'escape-html'

/**
 * @typedef TypeDefinition
 * @method {callback} action This action is executed to let the user select a resource
 * @param {string} icon Contains the icon css class for the type
 * @constructor
 */

/**
 * @type {TypeDefinition[]}
 **/
const types = {}

/**
 * Those translations will be used by the vue component but they should be shipped with the server
 * FIXME: Those translations should be added to the library
 * @returns {Array}
 */
export const l10nProjects = () => {
	return [
		t('core', 'Add to a project'),
		t('core', 'Show details'),
		t('core', 'Hide details'),
		t('core', 'Rename project'),
		t('core', 'Failed to rename the project'),
		t('core', 'Failed to create a project'),
		t('core', 'Failed to add the item to the project'),
		t('core', 'Connect items to a project to make them easier to find'),
		t('core', 'Type to search for existing projects'),
	]
}

export default {
	/**
	 *
	 * @param {string} type type
	 * @param {TypeDefinition} typeDefinition typeDefinition
	 */
	registerType(type, typeDefinition) {
		types[type] = typeDefinition
	},
	trigger(type) {
		return types[type].action()
	},
	getTypes() {
		return Object.keys(types)
	},
	getIcon(type) {
		return types[type].typeIconClass || ''
	},
	getLabel(type) {
		return escapeHTML(types[type].typeString || type)
	},
	getLink(type, id) {
		/* TODO: Allow action to be executed instead of href as well */
		return typeof types[type] !== 'undefined' ? types[type].link(id) : ''
	},
}
