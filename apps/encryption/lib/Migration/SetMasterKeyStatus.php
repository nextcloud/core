<?php
/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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

namespace OCA\Encryption\Migration;

use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Class SetPasswordColumn
 *
 * @package OCA\Files_Sharing\Migration
 */
class SetMasterKeyStatus implements IRepairStep {


	/** @var  IConfig */
	private $config;


	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * Returns the step's name
	 *
	 * @return string
	 * @since 9.1.0
	 */
	public function getName() {
		return 'Write default encryption module configuration to the database';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		if (!$this->shouldRun()) {
			return;
		}

		// if no config for the master key is set we set it explicitly to '0' in
		// order not to break old installations because the default changed to '1'.
		$configAlreadySet = $this->config->getAppValue('encryption', 'useMasterKey', false);
		if ($configAlreadySet === false) {
			$this->config->setAppValue('encryption', 'useMasterKey', '0');
		}
	}

	protected function shouldRun() {
		$appVersion = $this->config->getAppValue('encryption', 'installed_version', '0.0.0');
		return version_compare($appVersion, '2.0.0', '<');
	}

}
