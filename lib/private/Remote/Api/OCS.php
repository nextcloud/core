<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Remote\Api;

use GuzzleHttp\Exception\ClientException;
use OC\ForbiddenException;
use OC\Remote\User;
use OCP\API;
use OCP\Remote\Api\ICapabilitiesApi;
use OCP\Remote\Api\IUserApi;

class OCS extends ApiBase implements ICapabilitiesApi, IUserApi {
	/**
	 * @param string $method
	 * @param string $url
	 * @param array $body
	 * @param array $query
	 * @param array $headers
	 * @return array
	 * @throws ForbiddenException
	 * @throws NotFoundException
	 * @throws \Exception
	 */
	protected function request($method, $url, array $body = [], array $query = [], array $headers = []) {
		try {
			$response = json_decode(parent::request($method, 'ocs/v2.php/' . $url, $body, $query, $headers), true);
		} catch (ClientException $e) {
			if ($e->getResponse()->getStatusCode() === 404) {
				throw new NotFoundException();
			} else if ($e->getResponse()->getStatusCode() === 403 || $e->getResponse()->getStatusCode() === 401) {
				throw new ForbiddenException();
			} else {
				throw $e;
			}
		}
		if (!isset($response['ocs']) || !isset($response['ocs']['meta'])) {
			throw new \Exception('Invalid ocs response');
		}
		if ($response['ocs']['meta']['statuscode'] === API::RESPOND_UNAUTHORISED) {
			throw new ForbiddenException();
		}
		if ($response['ocs']['meta']['statuscode'] === API::RESPOND_NOT_FOUND) {
			throw new NotFoundException();
		}
		if ($response['ocs']['meta']['status'] !== 'ok') {
			throw new \Exception('Unknown ocs error ' . $response['ocs']['meta']['message']);
		}

		return $response['ocs']['data'];
	}

	/**
	 * @param array $data
	 * @param string $type
	 * @param string[] $keys
	 * @throws \Exception
	 */
	private function checkResponseArray(array $data, $type, array $keys) {
		foreach ($keys as $key) {
			if (!array_key_exists($key, $data)) {
				throw new \Exception('Invalid ' . $type . ' response, expected field ' . $key . ' not found');
			}
		}
	}

	public function getUser($userId) {
		$result = $this->request('get', 'cloud/users/' . $userId);
		$this->checkResponseArray($result, 'user', User::EXPECTED_KEYS);
		return new User($result);
	}

	/**
	 * @return array The capabilities in the form of [$appId => [$capability => $value]]
	 */
	public function getCapabilities() {
		$result = $this->request('get', 'cloud/capabilities');
		return $result['capabilities'];
	}
}
