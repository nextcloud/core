<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Security\Bruteforce;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CleanupJob extends TimedJob {

	/** @var IDBConnection */
	private $connection;

	public function __construct(ITimeFactory $time, IDBConnection $connection) {
		parent::__construct($time);
		$this->connection = $connection;

		// Run once a day
		$this->setInterval(3600 * 24);
	}

	protected function run($argument) {
		// Delete all entries more than 48 hours old
		$time = $this->time->getTime() - (48 * 3600);

		$qb = $this->connection->getQueryBuilder();
		$qb->delete('bruteforce_attempts')
			->where($qb->expr()->lt('occurred', $qb->createNamedParameter($time), IQueryBuilder::PARAM_INT));
		$qb->execute();
	}
}
