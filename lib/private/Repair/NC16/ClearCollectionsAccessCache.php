<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OC\Repair\NC16;

use OC\Collaboration\Resources\Manager;
use OCP\Collaboration\Resources\IManager;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ClearCollectionsAccessCache implements IRepairStep {

	/** @var IConfig */
	private $config;

	/** @var IManager|Manager */
	private $manager;

	public function __construct(IConfig $config, IManager $manager) {
		$this->config = $config;
		$this->manager = $manager;
	}

	public function getName(): string {
		return 'Clear access cache of projects';
	}

	private function shouldRun(): bool {
		$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0.0');
		return version_compare($versionFromBeforeUpdate, '17.0.0.3', '<=');
	}

	public function run(IOutput $output): void {
		if ($this->shouldRun()) {
			$this->manager->invalidateAccessCacheForAllCollections();
		}
	}
}
