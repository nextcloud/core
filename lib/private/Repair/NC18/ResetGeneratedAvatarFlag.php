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

namespace OC\Repair\NC18;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ResetGeneratedAvatarFlag implements IRepairStep {

	/** @var IConfig */
	private $config;
	/** @var IDBConnection */
	private $connection;

	public function __construct(IConfig $config,
								IDBConnection $connection) {
		$this->config = $config;
		$this->connection = $connection;
	}

	public function getName(): string {
		return 'Reset generated avatar flag';
	}

	private function shouldRun(): bool {
		$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0.0');
		return version_compare($versionFromBeforeUpdate, '18.0.0.5', '<=');
	}

	public function run(IOutput $output): void {
		if ($this->shouldRun()) {
			$query = $this->connection->getQueryBuilder();
			$query->delete('preferences')
				->where($query->expr()->eq('appid', $query->createNamedParameter('avatar')))
				->andWhere($query->expr()->eq('configkey', $query->createNamedParameter('generated')));
		}
	}
}
