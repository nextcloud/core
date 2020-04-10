<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCA\Files_Sharing\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCP\IDBConnection;
use OCP\OCS\IDiscoveryService;

class FederatedSharesDiscoverJob extends TimedJob {
	/** @var IDBConnection */
	private $connection;
	/** @var IDiscoveryService */
	private $discoveryService;

	public function __construct(IDBConnection $connection,
								IDiscoveryService $discoveryService) {
		$this->connection = $connection;
		$this->discoveryService = $discoveryService;

		$this->setInterval(86400);
	}

	public function run($argument) {
		$qb = $this->connection->getQueryBuilder();

		$qb->selectDistinct('remote')
			->from('share_external');

		$result = $qb->execute();
		while($row = $result->fetch()) {
			$this->discoveryService->discover($row['remote'], 'FEDERATED_SHARING', true);
		}
		$result->closeCursor();
	}
}
