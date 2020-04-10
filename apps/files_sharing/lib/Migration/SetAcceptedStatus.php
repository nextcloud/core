<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Joas Schilling <coding@schilljs.com>
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

namespace OCA\Files_Sharing\Migration;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Share\IShare;

class SetAcceptedStatus implements IRepairStep {

	/** @var IDBConnection */
	private $connection;

	/** @var  IConfig */
	private $config;


	public function __construct(IDBConnection $connection, IConfig $config) {
		$this->connection = $connection;
		$this->config = $config;
	}

	/**
	 * Returns the step's name
	 *
	 * @return string
	 * @since 9.1.0
	 */
	public function getName(): string {
		return 'Set existing shares as accepted';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output): void {
		if (!$this->shouldRun()) {
			return;
		}

		$query = $this->connection->getQueryBuilder();
		$query
			->update('share')
			->set('accepted', $query->createNamedParameter(IShare::STATUS_ACCEPTED))
			->where($query->expr()->in('share_type', $query->createNamedParameter([IShare::TYPE_USER, IShare::TYPE_GROUP, IShare::TYPE_USERGROUP], IQueryBuilder::PARAM_INT_ARRAY)));
		$query->execute();
	}

	protected function shouldRun() {
		$appVersion = $this->config->getAppValue('files_sharing', 'installed_version', '0.0.0');
		return version_compare($appVersion, '1.10.1', '<');
	}

}
