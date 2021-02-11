<?php
/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Michael Weimann <mail@michael-weimann.eu>
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

namespace OC\Repair;

use OC\Avatar\AvatarManager;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ClearGeneratedAvatarCache implements IRepairStep {

	/** @var AvatarManager */
	protected $avatarManager;

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config, AvatarManager $avatarManager) {
		$this->config = $config;
		$this->avatarManager = $avatarManager;
	}

	public function getName() {
		return 'Clear every generated avatar on major updates';
	}

	/**
	 * Check if this repair step should run
	 *
	 * @return boolean
	 */
	private function shouldRun() {
		$versionFromBeforeUpdate = $this->config->getSystemValue('version', '0.0.0.0');

		// was added to 15.0.0.4
		return version_compare($versionFromBeforeUpdate, '15.0.0.4', '<=');
	}

	public function run(IOutput $output) {
		if ($this->shouldRun()) {
			try {
				$this->avatarManager->clearCachedAvatars();
				$output->info('Avatar cache cleared');
			} catch (\Exception $e) {
				$output->warning('Unable to clear the avatar cache');
			}
		}
	}
}
