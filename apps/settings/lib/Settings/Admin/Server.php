<?php
/**
 * @copyright Copyright (c) 2016 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Settings\Settings\Admin;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Settings\ISettings;

class Server implements ISettings {

	/** @var IDBConnection */
	private $connection;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var IConfig */
	private $config;

	public function __construct(IDBConnection $connection,
								ITimeFactory $timeFactory,
								IConfig $config) {
		$this->connection = $connection;
		$this->timeFactory = $timeFactory;
		$this->config = $config;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$parameters = [
			// Background jobs
			'backgroundjobs_mode' => $this->config->getAppValue('core', 'backgroundjobs_mode', 'ajax'),
			'lastcron'            => $this->config->getAppValue('core', 'lastcron', false),
			'cronMaxAge'          => $this->cronMaxAge(),
			'cronErrors'          => $this->config->getAppValue('core', 'cronErrors'),
			'cli_based_cron_possible' => function_exists('posix_getpwuid'),
			'cli_based_cron_user' => function_exists('posix_getpwuid') ? posix_getpwuid(fileowner(\OC::$configDir . 'config.php'))['name'] : '',
		];

		return new TemplateResponse('settings', 'settings/admin/server', $parameters, '');
	}

	protected function cronMaxAge(): int {
		$query = $this->connection->getQueryBuilder();
		$query->select('last_checked')
			->from('jobs')
			->orderBy('last_checked', 'ASC')
			->setMaxResults(1);

		$result = $query->execute();
		if ($row = $result->fetch()) {
			$maxAge = (int) $row['last_checked'];
		} else {
			$maxAge = $this->timeFactory->getTime();
		}
		$result->closeCursor();

		return $maxAge;
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection(): string {
		return 'server';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority(): int {
		return 0;
	}
}
