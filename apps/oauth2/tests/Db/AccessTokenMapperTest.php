<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\OAuth2\Tests\Db;

use OCA\OAuth2\Db\AccessToken;
use OCA\OAuth2\Db\AccessTokenMapper;
use Test\TestCase;

/**
 * @group DB
 */
class AccessTokenMapperTest extends TestCase {
	/** @var AccessTokenMapper */
	private $accessTokenMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->accessTokenMapper = new AccessTokenMapper(\OC::$server->getDatabaseConnection());
	}

	public function testGetByCode() {
		$this->accessTokenMapper->deleteByClientId(1234);
		$token = new AccessToken();
		$token->setClientId(1234);
		$token->setTokenId((string)time());
		$token->setEncryptedToken('MyEncryptedToken');
		$token->setHashedCode(hash('sha512', 'MyAwesomeToken'));
		$this->accessTokenMapper->insert($token);
		$token->resetUpdatedFields();

		$result = $this->accessTokenMapper->getByCode('MyAwesomeToken');
		$this->assertEquals($token, $result);
		$this->accessTokenMapper->delete($token);
	}

	
	public function testDeleteByClientId() {
		$this->expectException(\OCA\OAuth2\Exceptions\AccessTokenNotFoundException::class);

		$this->accessTokenMapper->deleteByClientId(1234);
		$token = new AccessToken();
		$token->setClientId(1234);
		$token->setTokenId((string)time());
		$token->setEncryptedToken('MyEncryptedToken');
		$token->setHashedCode(hash('sha512', 'MyAwesomeToken'));
		$this->accessTokenMapper->insert($token);
		$token->resetUpdatedFields();
		$this->accessTokenMapper->deleteByClientId(1234);
		$this->accessTokenMapper->getByCode('MyAwesomeToken');
	}
}
