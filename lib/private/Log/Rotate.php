<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Log;

use OCP\Log\RotationTrait;

/**
 * This rotates the current logfile to a new name, this way the total log usage
 * will stay limited and older entries are available for a while longer.
 * For more professional log management set the 'logfile' config to a different
 * location and manage that with your own tools.
 */
class Rotate extends \OC\BackgroundJob\Job {
	use RotationTrait;

	public function run($dummy) {
		$systemConfig = \OC::$server->getSystemConfig();
		$this->filePath = $systemConfig->getValue('logfile', $systemConfig->getValue('datadirectory', \OC::$SERVERROOT . '/data') . '/nextcloud.log');

		$this->maxSize = \OC::$server->getConfig()->getSystemValue('log_rotate_size', 100 * 1024 * 1024);
		if($this->shouldRotateBySize()) {
			$rotatedFile = $this->rotate();
			$msg = 'Log file "'.$this->filePath.'" was over '.$this->maxSize.' bytes, moved to "'.$rotatedFile.'"';
			\OC::$server->getLogger()->warning($msg, ['app' => Rotate::class]);
		}
	}
}
