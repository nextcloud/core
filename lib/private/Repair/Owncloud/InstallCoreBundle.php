<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
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

use OC\App\AppStore\Bundles\BundleFetcher;
use OC\Installer;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class InstallCoreBundle implements IRepairStep {
	/** @var BundleFetcher */
	private $bundleFetcher;
	/** @var IConfig */
	private $config;
	/** @var Installer */
	private $installer;

	/**
	 * @param BundleFetcher $bundleFetcher
	 * @param IConfig $config
	 * @param Installer $installer
	 */
	public function __construct(BundleFetcher $bundleFetcher,
								IConfig $config,
								Installer $installer) {
		$this->bundleFetcher = $bundleFetcher;
		$this->config = $config;
		$this->installer = $installer;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return 'Install new core bundle components';
	}

	/**
	 * {@inheritdoc}
	 */
	public function run(IOutput $output) {
		$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0');

		if (version_compare($versionFromBeforeUpdate, '12.0.0.14', '>')) {
			return;
		}

		$defaultBundle = $this->bundleFetcher->getDefaultInstallationBundle();
		foreach ($defaultBundle as $bundle) {
			try {
				$this->installer->installAppBundle($bundle);
				$output->info('Successfully installed core app bundle.');
			} catch (\Exception $e) {
				$output->warning('Could not install core app bundle: ' . $e->getMessage());
			}
		}
	}
}
