<?php

declare(strict_types=1);

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

namespace OCA\DAV\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;

class CleanupInvitationTokenJob extends TimedJob {

	/** @var IDBConnection  */
	private $db;

	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(IDBConnection $db, ITimeFactory $timeFactory) {
		$this->db = $db;
		$this->timeFactory = $timeFactory;

		$this->setInterval(60 * 60 * 24);
	}

	public function run($argument) {
		$query = $this->db->getQueryBuilder();
		$query->delete('calendar_invitations')
			->where($query->expr()->lt('expiration',
				$query->createNamedParameter($this->timeFactory->getTime())))
			->execute();
	}
}
