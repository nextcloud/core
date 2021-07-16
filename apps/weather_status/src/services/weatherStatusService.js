/**
 * @copyright Copyright (c) 2020, Julien Veyssier
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

import HttpClient from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 *
 *
 * @param {String} lat the latitude
 * @param {String} lon the longitude
 * @returns {Promise<Object>}
 */
const setLocation = async(lat, lon) => {
	const url = generateOcsUrl('apps/weather_status/api/v1/location')
	const response = await HttpClient.put(url, {
		address: '',
		lat,
		lon,
	})

	return response.data.ocs.data
}

/**
 *
 * @param {String} address The location
 * @returns {Promise<Object>}
 */
const setAddress = async(address) => {
	const url = generateOcsUrl('apps/weather_status/api/v1/location')
	const response = await HttpClient.put(url, {
		address,
		lat: null,
		lon: null,
	})

	return response.data.ocs.data
}

/**
 *
 * @param {String} mode can be 1 browser or 2 custom
 * @returns {Promise<Object>}
 */
const setMode = async(mode) => {
	const url = generateOcsUrl('apps/weather_status/api/v1/mode')
	const response = await HttpClient.put(url, {
		mode,
	})

	return response.data.ocs.data
}

/**
 *
 * @returns {Promise<Object>}
 */
const usePersonalAddress = async() => {
	const url = generateOcsUrl('apps/weather_status/api/v1/use-personal')
	const response = await HttpClient.put(url)

	return response.data.ocs.data
}

/**
 * Fetches the location information for current user
 *
 * @returns {Promise<Object>}
 */
const getLocation = async() => {
	const url = generateOcsUrl('apps/weather_status/api/v1/location')
	const response = await HttpClient.get(url)

	return response.data.ocs.data
}

/**
 * Fetches the weather forecast
 *
 * @param {String} address The location
 * @returns {Promise<Object>}
 */
const fetchForecast = async() => {
	const url = generateOcsUrl('apps/weather_status/api/v1/forecast')
	const response = await HttpClient.get(url)

	return response.data.ocs.data
}

/**
 * Fetches the location favorites
 *
 * @param {String} address The location
 * @returns {Promise<Object>}
 */
const getFavorites = async() => {
	const url = generateOcsUrl('apps/weather_status/api/v1/favorites')
	const response = await HttpClient.get(url)

	return response.data.ocs.data
}

/**
 *
 * @param {Array} favorites List of favorite addresses
 * @returns {Promise<Object>}
 */
const saveFavorites = async(favorites) => {
	const url = generateOcsUrl('apps/weather_status/api/v1/favorites')
	const response = await HttpClient.put(url, {
		favorites,
	})

	return response.data.ocs.data
}

export {
	usePersonalAddress,
	setMode,
	getLocation,
	setLocation,
	setAddress,
	fetchForecast,
	getFavorites,
	saveFavorites,
}
