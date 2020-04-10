<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Files_Sharing\Listener;

use OCA\Files_Sharing\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\IManager;
use OCP\Share\IShare;

class UserShareAcceptanceListener implements IEventListener {

	/** @var IConfig */
	private $config;
	/** @var IManager */
	private $shareManager;
	/** @var IGroupManager */
	private $groupManager;

	public function __construct(IConfig $config, IManager $shareManager, IGroupManager $groupManager) {
		$this->config = $config;
		$this->shareManager = $shareManager;
		$this->groupManager = $groupManager;
	}

	public function handle(Event $event): void {
		if (!($event instanceof ShareCreatedEvent)) {
			return;
		}

		$share = $event->getShare();

		if ($share->getShareType() === IShare::TYPE_USER) {
			$this->handleAutoAccept($share, $share->getSharedWith());
		} else if ($share->getShareType() === IShare::TYPE_GROUP) {
			$group = $this->groupManager->get($share->getSharedWith());

			if ($group === null) {
				return;
			}

			$users = $group->getUsers();
			foreach ($users as $user) {
				$this->handleAutoAccept($share, $user->getUID());
			}
		}

	}

	private function handleAutoAccept(IShare $share, string $userId) {
		$defaultAcceptSystemConfig = $this->config->getSystemValueBool('sharing.enable_share_accept', false) ? 'no' : 'yes';
		$acceptDefault = $this->config->getUserValue($userId, Application::APP_ID, 'default_accept', $defaultAcceptSystemConfig) === 'yes';
		if (!$this->config->getSystemValueBool('sharing.force_share_accept', false) && $acceptDefault) {
			$this->shareManager->acceptShare($share, $userId);
		}
	}

}
