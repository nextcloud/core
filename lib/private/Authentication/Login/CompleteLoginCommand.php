<?php
/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

declare(strict_types=1);

namespace OC\Authentication\Login;

use OC\User\Session;

class CompleteLoginCommand extends ALoginCommand {

	/** @var Session */
	private $userSession;

	public function __construct(Session $userSession) {
		$this->userSession = $userSession;
	}

	public function process(LoginData $loginData): LoginResult {
		$this->userSession->completeLogin(
			$loginData->getUser(),
			[
				'loginName' => $loginData->getUsername(),
				'password' => $loginData->getPassword(),
			]
		);

		return $this->processNextOrFinishSuccessfully($loginData);
	}

}
