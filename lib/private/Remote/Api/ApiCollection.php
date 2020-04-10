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

use OCP\Http\Client\IClientService;
use OCP\Remote\Api\IApiCollection;
use OCP\Remote\ICredentials;
use OCP\Remote\IInstance;

class ApiCollection implements IApiCollection {
	/** @var IInstance */
	private $instance;
	/** @var ICredentials */
	private $credentials;
	/** @var IClientService */
	private $clientService;

	public function __construct(IInstance $instance, ICredentials $credentials, IClientService $clientService) {
		$this->instance = $instance;
		$this->credentials = $credentials;
		$this->clientService = $clientService;
	}

	public function getCapabilitiesApi() {
		return new OCS($this->instance, $this->credentials, $this->clientService);
	}

	public function getUserApi() {
		return new OCS($this->instance, $this->credentials, $this->clientService);
	}
}
