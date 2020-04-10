<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Repair\NC17;

use OC\Files\AppData\Factory;
use OC\Template\SCSSCacher;
use OCA\Theming\ThemingDefaults;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Support\Subscription\IRegistry;

/**
 * @deprecated - can be removed in 18
 */
class SetEnterpriseLogo implements IRepairStep {

	/** @var IConfig $config */
	private $config;

	/** @var IRegistry $subscriptionRegistry */
	private $subscriptionRegistry;

	/** @var IAppData $appData */
	private $appData;

	/** @var SCSSCacher $scssCacher */
	private $scssCacher;

	/** @var \OC_Defaults|ThemingDefaults */
	private $themingDefaults;

	public function getName(): string {
		return 'Sets the enterprise logo';
	}

	public function __construct(
		IConfig $config,
		IRegistry $subscriptionRegistry,
		Factory $appDataFactory,
		SCSSCacher $SCSSCacher,
		$ThemingDefaults
	) {
		$this->config = $config;
		$this->subscriptionRegistry = $subscriptionRegistry;
		$this->appData = $appDataFactory->get('theming');
		$this->scssCacher = $SCSSCacher;
		$this->themingDefaults = $ThemingDefaults;
	}

	public function run(IOutput $output): void {
		// only run once
		if ($this->config->getAppValue('core', 'enterpriseLogoChecked') === 'yes') {
			$output->info('Repair step already executed');
			return;
		}

		if (!$this->subscriptionRegistry->delegateHasValidSubscription()) {
			// no need to set the enterprise logo
			$this->config->setAppValue('core', 'enterpriseLogoChecked', 'yes');
			return;
		}

		if ($this->themingDefaults instanceof ThemingDefaults) {
			$output->info('Theming is enabled - trying to set logo.');
			try {
				$folder = $this->appData->getFolder('images');
			} catch (NotFoundException $e) {
				$folder = $this->appData->newFolder('images');
			}

			if (!$folder->fileExists('logo') || $folder->getFile('logo')->getSize() === 0) {
				$output->info('Logo does not exist yet - setting it.');

				if ($folder->fileExists('logo')) {
					$folder->getFile('logo')->delete();
				}
				$target = $folder->newFile('logo');

				$target->putContent(file_get_contents(__DIR__ . '/../../../../core/img/logo/logo-enterprise.png'));

				$this->themingDefaults->set('logoMime', 'image/png');

				$this->scssCacher->process(\OC::$SERVERROOT, 'core/css/css-variables.scss', 'core');
			} else {
				$output->info('Logo already set - skipping.');
			}
		} else {
			$output->info('Theming is not enabled - skipping.');
		}

		// if all were done, no need to redo the repair during next upgrade
		$this->config->setAppValue('core', 'enterpriseLogoChecked', 'yes');
	}
}
