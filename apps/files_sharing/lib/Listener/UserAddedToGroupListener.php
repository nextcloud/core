<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
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
use OCP\Group\Events\UserAddedEvent;
use OCP\IConfig;
use OCP\Share\IManager;
use OCP\Share\IShare;

class UserAddedToGroupListener implements IEventListener {

	/** @var IManager */
	private $shareManager;

	/** @var IConfig */
	private $config;

	public function __construct(IManager $shareManager, IConfig $config) {
		$this->shareManager = $shareManager;
		$this->config = $config;
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserAddedEvent)) {
			return;
		}

		$user = $event->getUser();
		$group = $event->getGroup();

		// This user doesn't have autoaccept so we can skip it all
		if (!$this->hasAutoAccept($user->getUID())) {
			return;
		}

		// Get all group shares this user has access to now to filter later
		$shares = $this->shareManager->getSharedWith($user->getUID(), IShare::TYPE_GROUP, null, -1);

		foreach ($shares as $share) {
			// If this is not the new group we can skip it
			if ($share->getSharedWith() !== $group->getGID()) {
				continue;
			}

			// Accept the share if needed
			$this->shareManager->acceptShare($share, $user->getUID());
		}
	}


	private function hasAutoAccept(string $userId): bool {
		$defaultAcceptSystemConfig = $this->config->getSystemValueBool('sharing.enable_share_accept', false) ? 'no' : 'yes';
		$acceptDefault = $this->config->getUserValue($userId, Application::APP_ID, 'default_accept', $defaultAcceptSystemConfig) === 'yes';
		return (!$this->config->getSystemValueBool('sharing.force_share_accept', false) && $acceptDefault);
	}
}
