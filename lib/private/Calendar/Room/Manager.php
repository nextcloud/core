<?php
/**
 * @copyright 2018, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Calendar\Room;

use OCP\Calendar\Room\IBackend;
use OCP\IServerContainer;

class Manager implements \OCP\Calendar\Room\IManager {

	/** @var IServerContainer */
	private $server;

	/** @var string[] holds all registered resource backends */
	private $backends = [];

	/** @var IBackend[] holds all backends that have been initialized already */
	private $initializedBackends = [];

	/**
	 * Manager constructor.
	 *
	 * @param IServerContainer $server
	 */
	public function __construct(IServerContainer $server) {
		$this->server = $server;
	}

	/**
	 * Registers a resource backend
	 *
	 * @param string $backendClass
	 * @return void
	 * @since 14.0.0
	 */
	public function registerBackend(string $backendClass) {
		$this->backends[$backendClass] = $backendClass;
	}

	/**
	 * Unregisters a resource backend
	 *
	 * @param string $backendClass
	 * @return void
	 * @since 14.0.0
	 */
	public function unregisterBackend(string $backendClass) {
		unset($this->backends[$backendClass], $this->initializedBackends[$backendClass]);
	}

	/**
	 * @return IBackend[]
	 * @throws \OCP\AppFramework\QueryException
	 * @since 14.0.0
	 */
	public function getBackends():array {
		foreach($this->backends as $backend) {
			if (isset($this->initializedBackends[$backend])) {
				continue;
			}

			$this->initializedBackends[$backend] = $this->server->query($backend);
		}

		return array_values($this->initializedBackends);
	}

	/**
	 * @param string $backendId
	 * @throws \OCP\AppFramework\QueryException
	 * @return IBackend|null
	 */
	public function getBackend($backendId) {
		$backends = $this->getBackends();
		foreach($backends as $backend) {
			if ($backend->getBackendIdentifier() === $backendId) {
				return $backend;
			}
		}

		return null;
	}

	/**
	 * removes all registered backend instances
	 * @return void
	 * @since 14.0.0
	 */
	public function clear() {
		$this->backends = [];
		$this->initializedBackends = [];
	}
}
