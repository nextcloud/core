<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Claas Augner <github@caugner.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Vincent Petry <vincent@nextcloud.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Settings\SetupChecks;

use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use OCP\IDBConnection;
use OCP\IL10N;

class SupportedDatabase {
	/** @var IL10N */
	private $l10n;
	/** @var IDBConnection */
	private $connection;

	private $checked = false;
	private $description = '';

	public function __construct(IL10N $l10n, IDBConnection $connection) {
		$this->l10n = $l10n;
		$this->connection = $connection;
	}

	public function check() {
		if ($this->checked === true) {
			return;
		}
		$this->checked = true;

		switch (get_class($this->connection->getDatabasePlatform())) {
			case MySQL80Platform::class: # extends MySQL57Platform
			case MySQL57Platform::class: # extends MySQLPlatform
			case MariaDb1027Platform::class: # extends MySQLPlatform
			case MySQLPlatform::class:
				$result = $this->connection->prepare("SHOW VARIABLES LIKE 'version';");
				$result->execute();
				$row = $result->fetch();
				$version = strtolower($row['Value']);

				if (strpos($version, 'mariadb') !== false) {
					if (version_compare($version, '10.2', '<')) {
						$this->description = $this->l10n->t('MariaDB version "%s" is used. Nextcloud 21 will no longer support this version and requires MariaDB 10.2 or higher.', $row['Value']);
						return;
					}
				} else {
					if (version_compare($version, '8', '<')) {
						$this->description = $this->l10n->t('MySQL version "%s" is used. Nextcloud 21 will no longer support this version and requires MySQL 8.0 or MariaDB 10.2 or higher.', $row['Value']);
						return;
					}
				}
				break;
			case SqlitePlatform::class:
				break;
			case PostgreSQL100Platform::class: # extends PostgreSQL94Platform
			case PostgreSQL94Platform::class:
				$result = $this->connection->prepare('SHOW server_version;');
				$result->execute();
				$row = $result->fetch();
				if (version_compare($row['server_version'], '9.6', '<')) {
					$this->description = $this->l10n->t('PostgreSQL version "%s" is used. Nextcloud 21 will no longer support this version and requires PostgreSQL 9.6 or higher.', $row['server_version']);
					return;
				}
				break;
			case OraclePlatform::class:
				break;
		}
	}

	public function description(): string {
		$this->check();
		return $this->description;
	}

	public function severity(): string {
		return 'info';
	}

	public function run(): bool {
		$this->check();
		return $this->description === '';
	}
}
