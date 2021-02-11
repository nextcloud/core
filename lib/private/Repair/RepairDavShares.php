<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OC\Repair;

use OCP\DB\Exception;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use function strlen;
use function substr;
use function urldecode;
use function urlencode;

class RepairDavShares implements IRepairStep {
	protected const GROUP_PRINCIPAL_PREFIX = 'principals/groups/';

	/** @var IConfig */
	private $config;
	/** @var IDBConnection */
	private $dbc;
	/** @var IGroupManager */
	private $groupManager;
	/** @var LoggerInterface */
	private $logger;
	/** @var bool */
	private $hintInvalidShares = false;

	public function __construct(
		IConfig $config,
		IDBConnection $dbc,
		IGroupManager $groupManager,
		LoggerInterface $logger
	) {
		$this->config = $config;
		$this->dbc = $dbc;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	public function getName() {
		return 'Repair DAV shares';
	}

	protected function repairUnencodedGroupShares() {
		$qb = $this->dbc->getQueryBuilder();
		$qb->select(['id', 'principaluri'])
			->from('dav_shares')
			->where($qb->expr()->like('principaluri', $qb->createNamedParameter(self::GROUP_PRINCIPAL_PREFIX . '%')));

		$updateQuery = $this->dbc->getQueryBuilder();
		$updateQuery->update('dav_shares')
			->set('principaluri', $updateQuery->createParameter('updatedPrincipalUri'))
			->where($updateQuery->expr()->eq('id', $updateQuery->createParameter('shareId')));

		$statement = $qb->execute();
		while ($share = $statement->fetch()) {
			$gid = substr($share['principaluri'], strlen(self::GROUP_PRINCIPAL_PREFIX));
			$decodedGid = urldecode($gid);
			$encodedGid = urlencode($gid);
			if ($gid === $encodedGid
				|| !$this->groupManager->groupExists($gid)
				|| ($gid !== $decodedGid && $this->groupManager->groupExists($decodedGid))
			) {
				$this->hintInvalidShares = $this->hintInvalidShares || $gid !== $encodedGid;
				continue;
			}

			// Repair when
			// + the group name needs encoding
			// + AND it is not encoded yet
			// + AND there are no ambivalent groups

			try {
				$fixedPrincipal = self::GROUP_PRINCIPAL_PREFIX . $encodedGid;
				$logParameters = [
					'app' => 'core',
					'id' => $share['id'],
					'old' => $share['principaluri'],
					'new' => $fixedPrincipal,
				];
				$updateQuery
					->setParameter('updatedPrincipalUri', $fixedPrincipal)
					->setParameter('shareId', $share['id'])
					->execute();
				$this->logger->info('Repaired principal for dav share {id} from {old} to {new}', $logParameters);
			} catch (Exception $e) {
				$logParameters['message'] = $e->getMessage();
				$logParameters['exception'] = $e;
				$this->logger->info('Could not repair principal for dav share {id} from {old} to {new}: {message}', $logParameters);
			}
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function run(IOutput $output) {
		$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0');
		if (version_compare($versionFromBeforeUpdate, '20.0.7', '<')
			&& $this->repairUnencodedGroupShares()
		) {
			$output->info('Repaired DAV group shares');
			if ($this->hintInvalidShares) {
				$output->info('Invalid shares might be left in the database, running "occ dav:remove-invalid-shares" can remove them.');
			}
		}
	}
}
