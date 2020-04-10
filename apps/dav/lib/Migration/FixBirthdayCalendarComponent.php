<?php
/**
 * @copyright Copyright (c) 2016, ownCloud GmbH.
 *
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Migration;

use OCA\DAV\CalDAV\BirthdayService;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class FixBirthdayCalendarComponent implements IRepairStep {

	/** @var IDBConnection */
	private $connection;

	/**
	 * FixBirthdayCalendarComponent constructor.
	 *
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @inheritdoc
	 */
	public function getName() {
		return 'Fix component of birthday calendars';
	}

	/**
	 * @inheritdoc
	 */
	public function run(IOutput $output) {
		$query = $this->connection->getQueryBuilder();
		$updated = $query->update('calendars')
			->set('components', $query->createNamedParameter('VEVENT'))
			->where($query->expr()->eq('uri', $query->createNamedParameter(BirthdayService::BIRTHDAY_CALENDAR_URI)))
			->execute();

		$output->info("$updated birthday calendars updated.");
	}
}
