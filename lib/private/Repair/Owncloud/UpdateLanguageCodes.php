<?php
/**
 * @copyright Copyright (c) 2016 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OC\Repair\Owncloud;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class UpdateLanguageCodes implements IRepairStep {
	/** @var IDBConnection */
	private $connection;

	/** @var IConfig */
	private $config;

	/**
	 * @param IDBConnection $connection
	 * @param IConfig $config
	 */
	public function __construct(IDBConnection $connection,
								IConfig $config) {
		$this->connection = $connection;
		$this->config = $config;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return 'Repair language codes';
	}

	/**
	 * {@inheritdoc}
	 */
	public function run(IOutput $output) {
		$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0');

		if (version_compare($versionFromBeforeUpdate, '12.0.0.13', '>')) {
			return;
		}

		$languages = [
			'bg_BG' => 'bg',
			'cs_CZ' => 'cs',
			'fi_FI' => 'fi',
			'hu_HU' => 'hu',
			'nb_NO' => 'nb',
			'sk_SK' => 'sk',
			'th_TH' => 'th',
		];

		foreach ($languages as $oldCode => $newCode) {
			$qb = $this->connection->getQueryBuilder();

			$affectedRows = $qb->update('preferences')
				->set('configvalue', $qb->createNamedParameter($newCode))
				->where($qb->expr()->eq('appid', $qb->createNamedParameter('core')))
				->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('lang')))
				->andWhere($qb->expr()->eq('configvalue', $qb->createNamedParameter($oldCode), IQueryBuilder::PARAM_STR))
				->execute();

			$output->info('Changed ' . $affectedRows . ' setting(s) from "' . $oldCode . '" to "' . $newCode . '" in preferences table.');
		}
	}
}
