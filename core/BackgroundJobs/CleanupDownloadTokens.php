<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Core\BackgroundJobs;

use OC\BackgroundJob\TimedJob;
use OC\Files\Utils\DownloadManager;
use OCP\IConfig;

class CleanupDownloadTokens extends TimedJob {
	private const INTERVAL_MINUTES = 24 * 60;
	/** @var IConfig */
	private $config;
	/** @var DownloadManager */
	private $downloadManager;

	public function __construct(IConfig $config, DownloadManager $downloadManager) {
		$this->interval = self::INTERVAL_MINUTES;
		$this->config = $config;
		$this->downloadManager = $downloadManager;
	}

	protected function run($argument) {
		$this->downloadManager->cleanupTokens();
	}
}
