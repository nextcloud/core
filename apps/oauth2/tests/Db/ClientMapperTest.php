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

use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use Test\TestCase;

/**
 * @group DB
 */
class ClientMapperTest extends TestCase {
	/** @var ClientMapper */
	private $clientMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->clientMapper = new ClientMapper(\OC::$server->getDatabaseConnection());
	}

	public function testGetByIdentifier() {
		$client = new Client();
		$client->setClientIdentifier('MyAwesomeClientIdentifier');
		$client->setName('Client Name');
		$client->setRedirectUri('https://example.com/');
		$client->setSecret('TotallyNotSecret');
		$this->clientMapper->insert($client);
		$client->resetUpdatedFields();
		$this->assertEquals($client, $this->clientMapper->getByIdentifier('MyAwesomeClientIdentifier'));
	}

	
	public function testGetByIdentifierNotExisting() {
		$this->expectException(\OCA\OAuth2\Exceptions\ClientNotFoundException::class);

		$this->clientMapper->getByIdentifier('MyTotallyNotExistingClient');
	}

	public function testGetByUid() {
		$client = new Client();
		$client->setClientIdentifier('MyNewClient');
		$client->setName('Client Name');
		$client->setRedirectUri('https://example.com/');
		$client->setSecret('TotallyNotSecret');
		$this->clientMapper->insert($client);
		$client->resetUpdatedFields();
		$this->assertEquals($client, $this->clientMapper->getByUid($client->getId()));
	}

	
	public function testGetByUidNotExisting() {
		$this->expectException(\OCA\OAuth2\Exceptions\ClientNotFoundException::class);

		$this->clientMapper->getByUid(1234);
	}

	public function testGetClients() {
		$this->assertSame('array', gettype($this->clientMapper->getClients()));
	}
}
