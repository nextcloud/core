<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Share;

/**
 * Class SetPasswordColumn
 *
 * @package OCA\Files_Sharing\Migration
 */
class SetPasswordColumn implements IRepairStep {

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
	public function getName() {
		return 'Copy the share password into the dedicated column';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		if (!$this->shouldRun()) {
			return;
		}

		$query = $this->connection->getQueryBuilder();
		$query
			->update('share')
			->set('password', 'share_with')
			->where($query->expr()->eq('share_type', $query->createNamedParameter(Share::SHARE_TYPE_LINK)))
			->andWhere($query->expr()->isNotNull('share_with'));
		$result = $query->execute();

		if ($result === 0) {
			// No link updated, no need to run the second query
			return;
		}

		$clearQuery = $this->connection->getQueryBuilder();
		$clearQuery
			->update('share')
			->set('share_with', $clearQuery->createNamedParameter(null))
			->where($clearQuery->expr()->eq('share_type', $clearQuery->createNamedParameter(Share::SHARE_TYPE_LINK)));

		$clearQuery->execute();

	}

	protected function shouldRun() {
		$appVersion = $this->config->getAppValue('files_sharing', 'installed_version', '0.0.0');
		return version_compare($appVersion, '1.4.0', '<');
	}

}
