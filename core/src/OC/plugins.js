/*
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

/**
 * @namespace OC.Plugins
 */
export default {

	/**
	 * @type Array.<OC.Plugin>
	 */
	_plugins: {},

	/**
	 * Register plugin
	 *
	 * @param {String} targetName app name / class name to hook into
	 * @param {OC.Plugin} plugin plugin
	 */
	register: function(targetName, plugin) {
		let plugins = this._plugins[targetName]
		if (!plugins) {
			plugins = this._plugins[targetName] = []
		}
		plugins.push(plugin)
	},

	/**
	 * Returns all plugin registered to the given target
	 * name / app name / class name.
	 *
	 * @param {String} targetName app name / class name to hook into
	 * @returns {Array.<OC.Plugin>} array of plugins
	 */
	getPlugins: function(targetName) {
		return this._plugins[targetName] || []
	},

	/**
	 * Call attach() on all plugins registered to the given target name.
	 *
	 * @param {String} targetName app name / class name
	 * @param {Object} targetObject to be extended
	 * @param {Object} [options] options
	 */
	attach: function(targetName, targetObject, options) {
		const plugins = this.getPlugins(targetName)
		for (let i = 0; i < plugins.length; i++) {
			if (plugins[i].attach) {
				plugins[i].attach(targetObject, options)
			}
		}
	},

	/**
	 * Call detach() on all plugins registered to the given target name.
	 *
	 * @param {String} targetName app name / class name
	 * @param {Object} targetObject to be extended
	 * @param {Object} [options] options
	 */
	detach: function(targetName, targetObject, options) {
		const plugins = this.getPlugins(targetName)
		for (let i = 0; i < plugins.length; i++) {
			if (plugins[i].detach) {
				plugins[i].detach(targetObject, options)
			}
		}
	},

}
