<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\User_LDAP\Migration;

use OCA\User_LDAP\Helper;
use OCA\User_LDAP\LDAPProviderFactory;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class SetDefaultProvider implements IRepairStep {

	/** @var IConfig */
	private $config;

	/** @var Helper */
	private $helper;

	public function __construct(IConfig $config,
								Helper $helper) {
		$this->config = $config;
		$this->helper = $helper;
	}

	public function getName(): string {
		return 'Set default LDAP provider';
	}

	public function run(IOutput $output): void {
		$current = $this->config->getSystemValue('ldapProviderFactory', null);
		if ($current === null) {
			$this->config->setSystemValue('ldapProviderFactory', LDAPProviderFactory::class);
		}
	}
}
