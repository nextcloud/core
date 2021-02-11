<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, ownCloud GmbH
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\Command;

use OCA\DAV\Connector\Sabre\Principal;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RemoveInvalidShares - removes shared calendars and addressbook which
 * have no matching principal. Happened because of a bug in the calendar app.
 */
class RemoveInvalidShares extends Command {

	/** @var IDBConnection */
	private $connection;
	/** @var Principal */
	private $principalBackend;

	public function __construct(IDBConnection $connection,
								Principal $principalBackend) {
		parent::__construct();

		$this->connection = $connection;
		$this->principalBackend = $principalBackend;
	}

	protected function configure() {
		$this
			->setName('dav:remove-invalid-shares')
			->setDescription('Remove invalid dav shares');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$query = $this->connection->getQueryBuilder();
		$result = $query->selectDistinct('principaluri')
			->from('dav_shares')
			->execute();

		while ($row = $result->fetch()) {
			$principaluri = $row['principaluri'];
			$p = $this->principalBackend->getPrincipalByPath($principaluri);
			if ($p === null) {
				$this->deleteSharesForPrincipal($principaluri);
			}
		}

		$result->closeCursor();
		return 0;
	}

	/**
	 * @param string $principaluri
	 */
	private function deleteSharesForPrincipal($principaluri) {
		$delete = $this->connection->getQueryBuilder();
		$delete->delete('dav_shares')
			->where($delete->expr()->eq('principaluri', $delete->createNamedParameter($principaluri)));
		$delete->execute();
	}
}
