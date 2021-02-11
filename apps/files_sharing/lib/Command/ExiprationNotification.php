<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Files_Sharing\Command;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\Notification\IManager as NotificationManager;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExiprationNotification extends Command {
	/** @var NotificationManager */
	private $notificationManager;
	/** @var IDBConnection */
	private $connection;
	/** @var ITimeFactory */
	private $time;
	/** @var ShareManager */
	private $shareManager;

	public function __construct(ITimeFactory $time,
								NotificationManager $notificationManager,
								IDBConnection $connection,
								ShareManager $shareManager) {
		parent::__construct();

		$this->notificationManager = $notificationManager;
		$this->connection = $connection;
		$this->time = $time;
		$this->shareManager = $shareManager;
	}

	protected function configure() {
		$this
			->setName('sharing:expiration-notification')
			->setDescription('Notify share initiators when a share will expire the next day.');
	}

	public function execute(InputInterface $input, OutputInterface $output): int {
		//Current time
		$minTime = $this->time->getDateTime();
		$minTime->add(new \DateInterval('P1D'));
		$minTime->setTime(0,0,0);

		$maxTime = clone $minTime;
		$maxTime->setTime(23, 59, 59);

		$shares = $this->shareManager->getAllShares();

		$now = $this->time->getDateTime();

		/** @var IShare $share */
		foreach ($shares as $share) {
			if ($share->getExpirationDate() === null
				|| $share->getExpirationDate()->getTimestamp() < $minTime->getTimestamp()
				|| $share->getExpirationDate()->getTimestamp() > $maxTime->getTimestamp()) {
				continue;
			}

			$notification = $this->notificationManager->createNotification();
			$notification->setApp('files_sharing')
				->setDateTime($now)
				->setObject('share', $share->getFullId())
				->setSubject('expiresTomorrow');

			// Only send to initiator for now
			$notification->setUser($share->getSharedBy());
			$this->notificationManager->notify($notification);
		}
		return 0;
	}
}
