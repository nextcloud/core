<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\Security\IdentityProof;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserManager;

class Signer {
	/** @var Manager */
	private $keyManager;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var IUserManager */
	private $userManager;

	/**
	 * @param Manager $keyManager
	 * @param ITimeFactory $timeFactory
	 * @param IUserManager $userManager
	 */
	public function __construct(Manager $keyManager,
								ITimeFactory $timeFactory,
								IUserManager $userManager) {
		$this->keyManager = $keyManager;
		$this->timeFactory = $timeFactory;
		$this->userManager = $userManager;
	}

	/**
	 * Returns a signed blob for $data
	 *
	 * @param string $type
	 * @param array $data
	 * @param IUser $user
	 * @return array ['message', 'signature']
	 */
	public function sign(string $type, array $data, IUser $user): array {
		$privateKey = $this->keyManager->getKey($user)->getPrivate();
		$data = [
			'data' => $data,
			'type' => $type,
			'signer' => $user->getCloudId(),
			'timestamp' => $this->timeFactory->getTime(),
		];
		openssl_sign(json_encode($data), $signature, $privateKey, OPENSSL_ALGO_SHA512);

		return [
			'message' => $data,
			'signature' => base64_encode($signature),
		];
	}

	/**
	 * Whether the data is signed properly
	 *
	 * @param array $data
	 * @return bool
	 */
	public function verify(array $data): bool {
		if(isset($data['message'])
			&& isset($data['signature'])
			&& isset($data['message']['signer'])
		) {
			$location = strrpos($data['message']['signer'], '@');
			$userId = substr($data['message']['signer'], 0, $location);

			$user = $this->userManager->get($userId);
			if($user !== null) {
				$key = $this->keyManager->getKey($user);
				return (bool)openssl_verify(
					json_encode($data['message']),
					base64_decode($data['signature']),
					$key->getPublic(),
					OPENSSL_ALGO_SHA512
				);
			}
		}

		return false;
	}
}
