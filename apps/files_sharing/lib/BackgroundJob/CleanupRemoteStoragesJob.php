<?php

declare(strict_types=1);

/**
 * @copyright (c) 2020, Nils Werner <sammellog@gmail.com>
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files_Sharing\BackgroundJob;

use OCA\Files_Sharing\Command\CleanupRemoteStorages;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Background job for the Command CleanupRemoteStorages
 */
class CleanupRemoteStoragesJob extends TimedJob
{
	/**
	 * @var CleanupRemoteStorages
	 */
	private CleanupRemoteStorages $remoteStorages;

	/**
	 * CleanupRemoteStoragesJob constructor.
	 * @param ITimeFactory $time
	 * @param CleanupRemoteStorages $remoteStorages
	 */
	private function __construct(ITimeFactory $time, CleanupRemoteStorages $remoteStorages)
	{
		parent::__construct($time);
		$this->remoteStorages = $remoteStorages;

		// Only once a week
		parent::setInterval(604800);
	}

	/**
	 * @param $argument
	 * @return void
	 */
	protected function run($argument)
	{
		$input = new ArrayInput(array(
			'command' => 'sharing:cleanup-remote-storages',
			'--dry-run' => ""
		));
		$output = new NullOutput();
		$this->remoteStorages->execute($input, $output);
	}
}
