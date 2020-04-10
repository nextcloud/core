<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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
namespace OCA\Files\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCP\DirectEditing\IManager;

class CleanupDirectEditingTokens extends TimedJob {

	private const INTERVAL_MINUTES = 15 * 60;

	/**
	 * @var IManager
	 */
	private $manager;

	public function __construct(IManager $manager) {
		$this->interval = self::INTERVAL_MINUTES;
		$this->manager = $manager;
	}

	/**
	 * Makes the background job do its work
	 *
	 * @param array $argument unused argument
	 * @throws \Exception
	 */
	public function run($argument) {
		$this->manager->cleanup();
	}
}
