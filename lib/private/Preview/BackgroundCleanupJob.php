<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Preview;

use OC\BackgroundJob\TimedJob;
use OC\Files\AppData\Factory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IDBConnection;

class BackgroundCleanupJob extends TimedJob {

	/** @var IDBConnection */
	private $connection;

	/** @var Factory */
	private $appDataFactory;

	/** @var bool */
	private $isCLI;

	public function __construct(IDBConnection $connection,
								Factory $appDataFactory,
								bool $isCLI) {
		// Run at most once an hour
		$this->setInterval(3600);

		$this->connection = $connection;
		$this->appDataFactory = $appDataFactory;
		$this->isCLI = $isCLI;
	}

	public function run($argument) {
		$previews = $this->appDataFactory->get('preview');

		$previewFodlerId = $previews->getId();

		$qb = $this->connection->getQueryBuilder();
		$qb->select('a.name')
			->from('filecache', 'a')
			->leftJoin('a', 'filecache', 'b', $qb->expr()->eq(
				$qb->expr()->castColumn('a.name', IQueryBuilder::PARAM_INT), 'b.fileid'
			))
			->where(
				$qb->expr()->isNull('b.fileid')
			)->andWhere(
				$qb->expr()->eq('a.parent', $qb->createNamedParameter($previewFodlerId))
			);

		if (!$this->isCLI) {
			$qb->setMaxResults(10);
		}

		$cursor = $qb->execute();

		while ($row = $cursor->fetch()) {
			try {
				$preview = $previews->getFolder($row['name']);
				$preview->delete();
			} catch (NotFoundException $e) {
				// continue
			} catch (NotPermittedException $e) {
				// continue
			}
		}

		$cursor->closeCursor();
	}
}
