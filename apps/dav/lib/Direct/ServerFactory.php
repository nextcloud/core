<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\Direct;

use OC\Security\Bruteforce\Throttler;
use OCA\DAV\Db\DirectMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

class ServerFactory {
	/** @var IConfig */
	private $config;
	/** @var IL10N */
	private $l10n;

	public function __construct(IConfig $config, IL10N $l10n) {
		$this->config = $config;
		$this->l10n = $l10n;
	}

	public function createServer(string $baseURI,
								 string $requestURI,
								 IRootFolder $rootFolder,
								 DirectMapper $mapper,
								 ITimeFactory $timeFactory,
								 Throttler $throttler,
								 IRequest $request): Server {
		$home = new DirectHome($rootFolder, $mapper, $timeFactory, $throttler, $request);
		$server = new Server($home);

		$server->httpRequest->setUrl($requestURI);
		$server->setBaseUri($baseURI);

		$server->addPlugin(new \OCA\DAV\Connector\Sabre\MaintenancePlugin($this->config, $this->l10n));

		return $server;
	}
}
