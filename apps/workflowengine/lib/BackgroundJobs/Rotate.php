<?php
/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\WorkflowEngine\BackgroundJobs;

use OC\BackgroundJob\TimedJob;
use OCA\WorkflowEngine\AppInfo\Application;
use OCP\Log\RotationTrait;

class Rotate extends TimedJob {
	use RotationTrait;

	public function __construct() {
		$this->setInterval(60*60*3);
	}

	protected function run($argument) {
		$config = \OC::$server->getConfig();
		$default = $config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data') . '/flow.log';
		$this->filePath = trim((string)$config->getAppValue(Application::APP_ID, 'logfile', $default));

		if($this->filePath === '') {
			// disabled, nothing to do
			return;
		}

		$this->maxSize = $config->getSystemValue('log_rotate_size', 100 * 1024 * 1024);

		if($this->shouldRotateBySize()) {
			$this->rotate();
		}
	}
}
