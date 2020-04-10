<?php
/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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

declare(strict_types=1);

namespace OC\Authentication\Login;

use OC\Hooks\PublicEmitter;
use OCP\IUserManager;

class PreLoginHookCommand extends ALoginCommand {

	/** @var IUserManager */
	private $userManager;

	public function __construct(IUserManager $userManager) {
		$this->userManager = $userManager;
	}

	public function process(LoginData $loginData): LoginResult {
		if ($this->userManager instanceof PublicEmitter) {
			$this->userManager->emit(
				'\OC\User',
				'preLogin',
				[
					$loginData->getUsername(),
					$loginData->getPassword(),
				]
			);
		}

		return $this->processNextOrFinishSuccessfully($loginData);
	}
}
