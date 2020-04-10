<?php
/**
 * @copyright 2017 Joas Schilling <coding@schilljs.com>
 *
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

namespace OCA\DAV\Migration;

use OCA\DAV\CalDAV\CalDavBackend;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Sabre\VObject\InvalidDataException;

class CalDAVRemoveEmptyValue implements IRepairStep {

	/** @var IDBConnection */
	private $db;

	/** @var CalDavBackend */
	private $calDavBackend;

	/** @var ILogger */
	private $logger;

	/**
	 * @param IDBConnection $db
	 * @param CalDavBackend $calDavBackend
	 * @param ILogger $logger
	 */
	public function __construct(IDBConnection $db, CalDavBackend $calDavBackend, ILogger $logger) {
		$this->db = $db;
		$this->calDavBackend = $calDavBackend;
		$this->logger = $logger;
	}

	public function getName() {
		return 'Fix broken values of calendar objects';
	}

	public function run(IOutput $output) {
		$pattern = ';VALUE=:';
		$count = $warnings = 0;

		$objects = $this->getInvalidObjects($pattern);

		$output->startProgress(count($objects));
		foreach ($objects as $row) {
			$calObject = $this->calDavBackend->getCalendarObject((int)$row['calendarid'], $row['uri']);
			$data = preg_replace('/' . $pattern . '/', ':', $calObject['calendardata']);

			if ($data !== $calObject['calendardata']) {
				$output->advance();

				try {
					$this->calDavBackend->getDenormalizedData($data);
				} catch (InvalidDataException $e) {
					$this->logger->info('Calendar object for calendar {cal} with uri {uri} still invalid', [
						'app' => 'dav',
						'cal' => (int)$row['calendarid'],
						'uri' => $row['uri'],
					]);
					$warnings++;
					continue;
				}

				$this->calDavBackend->updateCalendarObject((int)$row['calendarid'], $row['uri'], $data);
				$count++;
			}
		}
		$output->finishProgress();

		if ($warnings > 0) {
			$output->warning(sprintf('%d events could not be updated, see log file for more information', $warnings));
		}
		if ($count > 0) {
			$output->info(sprintf('Updated %d events', $count));
		}
	}

	protected function getInvalidObjects($pattern) {
		$query = $this->db->getQueryBuilder();
		$query->select(['calendarid', 'uri'])
			->from('calendarobjects')
			->where($query->expr()->like(
				'calendardata',
				$query->createNamedParameter(
					'%' . $this->db->escapeLikeParameter($pattern) . '%',
					IQueryBuilder::PARAM_STR
				),
				IQueryBuilder::PARAM_STR
			));

		$result = $query->execute();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}
}
