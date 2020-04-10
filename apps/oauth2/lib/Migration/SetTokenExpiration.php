<?php

declare(strict_types=1);

/**
 * @copyright Copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\OAuth2\Migration;

use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Token\IProvider as TokenProvider;
use OCA\OAuth2\Db\AccessToken;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class SetTokenExpiration implements IRepairStep {

	/** @var IDBConnection */
	private $connection;

	/** @var ITimeFactory */
	private $time;

	/** @var TokenProvider */
	private $tokenProvider;

	public function __construct(IDBConnection $connection,
								ITimeFactory $timeFactory,
								TokenProvider $tokenProvider) {
		$this->connection = $connection;
		$this->time = $timeFactory;
		$this->tokenProvider = $tokenProvider;
	}

	public function getName(): string {
		return 'Update OAuth token expiration times';
	}

	public function run(IOutput $output) {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
			->from('oauth2_access_tokens');

		$cursor = $qb->execute();

		while($row = $cursor->fetch()) {
			$token = AccessToken::fromRow($row);
			try {
				$appToken = $this->tokenProvider->getTokenById($token->getTokenId());
				$appToken->setExpires($this->time->getTime() + 3600);
				$this->tokenProvider->updateToken($appToken);
			} catch (InvalidTokenException $e) {
				//Skip this token
			}
		}
		$cursor->closeCursor();
	}

}
