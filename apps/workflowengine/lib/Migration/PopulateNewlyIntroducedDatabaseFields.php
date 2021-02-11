<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 *
 */

namespace OCA\WorkflowEngine\Migration;

use OCP\DB\IResult;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\WorkflowEngine\IManager;

class PopulateNewlyIntroducedDatabaseFields implements IRepairStep {

	/** @var IDBConnection */
	private $dbc;

	public function __construct(IDBConnection $dbc) {
		$this->dbc = $dbc;
	}

	public function getName() {
		return 'Populating added database structures for workflows';
	}

	public function run(IOutput $output) {
		$result = $this->getIdsWithoutScope();

		$this->populateScopeTable($result);

		$result->closeCursor();
	}

	protected function populateScopeTable(IResult $ids): void {
		$qb = $this->dbc->getQueryBuilder();

		$insertQuery = $qb->insert('flow_operations_scope');
		while ($id = $ids->fetchOne()) {
			$insertQuery->values(['operation_id' => $qb->createNamedParameter($id), 'type' => IManager::SCOPE_ADMIN]);
			$insertQuery->execute();
		}
	}

	protected function getIdsWithoutScope(): IResult {
		$qb = $this->dbc->getQueryBuilder();
		$selectQuery = $qb->select('o.id')
			->from('flow_operations', 'o')
			->leftJoin('o', 'flow_operations_scope', 's', $qb->expr()->eq('o.id', 's.operation_id'))
			->where($qb->expr()->isNull('s.operation_id'));
		// The left join operation is not necessary, usually, but it's a safe-guard
		// in case the repair step is executed multiple times for whatever reason.

		/** @var IResult $result */
		$result = $selectQuery->execute();
		return $result;
	}
}
