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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\User_LDAP\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Class RmRefreshTime
 *
 * this can be removed with Nextcloud 21
 *
 * @package OCA\User_LDAP\Migration
 */
class RemoveRefreshTime implements IRepairStep {

	/** @var IDBConnection */
	private $dbc;
	/** @var IConfig */
	private $config;

	public function __construct(IDBConnection $dbc, IConfig $config) {
		$this->dbc = $dbc;
		$this->config = $config;
	}

	public function getName() {
		return 'Remove deprecated refresh time markers for LDAP user records';
	}

	public function run(IOutput $output) {
		$this->config->deleteAppValue('user_ldap', 'updateAttributesInterval');

		$qb = $this->dbc->getQueryBuilder();
		$qb->delete('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('user_ldap')))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('lastFeatureRefresh')))
			->execute();
	}
}
