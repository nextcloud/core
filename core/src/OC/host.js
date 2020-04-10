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
 * Protocol that is used to access this Nextcloud instance
 * @returns {string} Used protocol
 * @deprecated 17.0.0 use window.location.protocol directly
 */
export const getProtocol = () => window.location.protocol.split(':')[0]

/**
 * Returns the host used to access this Nextcloud instance
 * Host is sometimes the same as the hostname but now always.
 *
 * Examples:
 * http://example.com => example.com
 * https://example.com => example.com
 * http://example.com:8080 => example.com:8080
 *
 * @returns {string} host
 *
 * @since 8.2
 * @deprecated 17.0.0 use window.location.host directly
 */
export const getHost = () => window.location.host

/**
 * Returns the hostname used to access this Nextcloud instance
 * The hostname is always stripped of the port
 *
 * @returns {string} hostname
 * @since 9.0
 * @deprecated 17.0.0 use window.location.hostname directly
 */
export const getHostName = () => window.location.hostname

/**
 * Returns the port number used to access this Nextcloud instance
 *
 * @returns {int} port number
 *
 * @since 8.2
 * @deprecated 17.0.0 use window.location.port directly
 */
export const getPort = () => window.location.port
