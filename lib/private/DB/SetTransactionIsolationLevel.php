<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Daniel Kesselberg <mail@danielkesselberg.de>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace OC\DB;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\TransactionIsolationLevel;

class SetTransactionIsolationLevel implements EventSubscriber {
	/**
	 * @param ConnectionEventArgs $args
	 * @return void
	 */
	public function postConnect(ConnectionEventArgs $args) {
		$args->getConnection()->setTransactionIsolation(TransactionIsolationLevel::READ_COMMITTED);
	}

	public function getSubscribedEvents() {
		return [Events::postConnect];
	}
}
