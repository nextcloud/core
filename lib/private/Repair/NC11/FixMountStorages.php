<?php
/**
 * @copyright 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Repair\NC11;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class FixMountStorages implements IRepairStep {

	/** @var IDBConnection */
	private $db;

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'Fix potential broken mount points';
	}

	public function run(IOutput $output) {
		$query = $this->db->getQueryBuilder();
		$query->select('m.id', 'f.storage')
			->from('mounts', 'm')
			->leftJoin('m', 'filecache', 'f', $query->expr()->eq('m.root_id', 'f.fileid'))
			->where($query->expr()->neq('m.storage_id', 'f.storage'));

		$update = $this->db->getQueryBuilder();
		$update->update('mounts')
			->set('storage_id', $update->createParameter('storage'))
			->where($query->expr()->eq('id', $update->createParameter('mount')));

		$result = $query->execute();
		$entriesUpdated = 0;
		while ($row = $result->fetch()) {
			$update->setParameter('storage', $row['storage'], IQueryBuilder::PARAM_INT)
				->setParameter('mount', $row['id'], IQueryBuilder::PARAM_INT);
			$update->execute();
			$entriesUpdated++;
		}
		$result->closeCursor();

		if ($entriesUpdated > 0) {
			$output->info($entriesUpdated . ' mounts updated');
			return;
		}

		$output->info('No mounts updated');
	}
}
