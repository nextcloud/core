<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Repair\NC17;

use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Support\Subscription\IRegistry;

/**
 * @deprecated - can be removed in 18
 */
class SwitchUpdateChannel implements IRepairStep {

	/** @var IConfig */
	private $config;

	/** @var IRegistry */
	private $subscriptionRegistry;

	public function __construct(IConfig $config, IRegistry $subscriptionRegistry) {
		$this->config = $config;
		$this->subscriptionRegistry = $subscriptionRegistry;
	}

	public function getName(): string {
		return 'Switches from deprecated "production" to "stable" update channel';
	}

	public function run(IOutput $output): void {
		$currentChannel = $this->config->getSystemValue('updater.release.channel', 'stable');

		if ($currentChannel === 'production') {
			if ($this->subscriptionRegistry->delegateHasValidSubscription()) {
				$this->config->setSystemValue('updater.release.channel', 'enterprise');
			} else {
				$this->config->setSystemValue('updater.release.channel', 'stable');
			}
		}
	}
}
