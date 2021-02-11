<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OC\Authentication\Listeners;

use OC\Authentication\Events\LoginFailed;
use OCP\Authentication\Events\LoginFailedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserManager;
use OCP\Util;

/**
 * @template-implements IEventListener<\OC\Authentication\Events\LoginFailed>
 */
class LoginFailedListener implements IEventListener {

	/** @var IEventDispatcher */
	private $dispatcher;

	/** @var IUserManager */
	private $userManager;

	public function __construct(IEventDispatcher $dispatcher, IUserManager $userManager) {
		$this->dispatcher = $dispatcher;
		$this->userManager = $userManager;
	}

	public function handle(Event $event): void {
		if (!($event instanceof LoginFailed)) {
			return;
		}

		$uid = $event->getLoginName();
		Util::emitHook(
			'\OCA\Files_Sharing\API\Server2Server',
			'preLoginNameUsedAsUserName',
			['uid' => &$uid]
		);
		if ($this->userManager->userExists($uid)) {
			$this->dispatcher->dispatchTyped(new LoginFailedEvent($uid));
		}
	}
}
