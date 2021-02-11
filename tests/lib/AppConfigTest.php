<?php
/**
 * Copyright (c) 2013 Christopher Schäpers <christopher@schaepers.it>
 * Copyright (c) 2013 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OC\AppConfig;
use OC\DB\Connection;
use OCP\IConfig;

/**
 * Class AppConfigTest
 *
 * @group DB
 *
 * @package Test
 */
class AppConfigTest extends TestCase {
	/** @var \OCP\IAppConfig */
	protected $appConfig;

	/** @var Connection */
	protected $connection;

	protected $originalConfig;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->get(Connection::class);
		$sql = $this->connection->getQueryBuilder();
		$sql->select('*')
			->from('appconfig');
		$result = $sql->execute();
		$this->originalConfig = $result->fetchAll();
		$result->closeCursor();

		$sql = $this->connection->getQueryBuilder();
		$sql->delete('appconfig');
		$sql->execute();

		$this->overwriteService(AppConfig::class, new \OC\AppConfig($this->connection));

		$sql = $this->connection->getQueryBuilder();
		$sql->insert('appconfig')
			->values([
				'appid' => $sql->createParameter('appid'),
				'configkey' => $sql->createParameter('configkey'),
				'configvalue' => $sql->createParameter('configvalue'),
			]);

		$sql->setParameters([
			'appid' => 'testapp',
			'configkey' => 'enabled',
			'configvalue' => 'true',
		])->execute();
		$sql->setParameters([
			'appid' => 'testapp',
			'configkey' => 'installed_version',
			'configvalue' => '1.2.3',
		])->execute();
		$sql->setParameters([
			'appid' => 'testapp',
			'configkey' => 'depends_on',
			'configvalue' => 'someapp',
		])->execute();
		$sql->setParameters([
			'appid' => 'testapp',
			'configkey' => 'deletethis',
			'configvalue' => 'deletethis',
		])->execute();
		$sql->setParameters([
			'appid' => 'testapp',
			'configkey' => 'key',
			'configvalue' => 'value',
		])->execute();

		$sql->setParameters([
			'appid' => 'someapp',
			'configkey' => 'key',
			'configvalue' => 'value',
		])->execute();
		$sql->setParameters([
			'appid' => 'someapp',
			'configkey' => 'otherkey',
			'configvalue' => 'othervalue',
		])->execute();

		$sql->setParameters([
			'appid' => '123456',
			'configkey' => 'key',
			'configvalue' => 'value',
		])->execute();
		$sql->setParameters([
			'appid' => '123456',
			'configkey' => 'enabled',
			'configvalue' => 'false',
		])->execute();

		$sql->setParameters([
			'appid' => 'anotherapp',
			'configkey' => 'key',
			'configvalue' => 'value',
		])->execute();
		$sql->setParameters([
			'appid' => 'anotherapp',
			'configkey' => 'enabled',
			'configvalue' => 'false',
		])->execute();
	}

	protected function tearDown(): void {
		$sql = $this->connection->getQueryBuilder();
		$sql->delete('appconfig');
		$sql->execute();

		$sql = $this->connection->getQueryBuilder();
		$sql->insert('appconfig')
			->values([
				'appid' => $sql->createParameter('appid'),
				'configkey' => $sql->createParameter('configkey'),
				'configvalue' => $sql->createParameter('configvalue'),
			]);

		foreach ($this->originalConfig as $configs) {
			$sql->setParameter('appid', $configs['appid'])
				->setParameter('configkey', $configs['configkey'])
				->setParameter('configvalue', $configs['configvalue']);
			$sql->execute();
		}

		$this->restoreService(AppConfig::class);
		parent::tearDown();
	}

	public function testGetApps() {
		$config = new \OC\AppConfig(\OC::$server->get(Connection::class));

		$this->assertEqualsCanonicalizing([
			'anotherapp',
			'someapp',
			'testapp',
			123456,
		], $config->getApps());
	}

	public function testGetKeys() {
		$config = new \OC\AppConfig(\OC::$server->get(Connection::class));

		$keys = $config->getKeys('testapp');
		$this->assertEqualsCanonicalizing([
			'deletethis',
			'depends_on',
			'enabled',
			'installed_version',
			'key',
		], $keys);
	}

	public function testGetValue() {
		$config = new \OC\AppConfig(\OC::$server->get(Connection::class));

		$value = $config->getValue('testapp', 'installed_version');
		$this->assertConfigKey('testapp', 'installed_version', $value);

		$value = $config->getValue('testapp', 'nonexistant');
		$this->assertNull($value);

		$value = $config->getValue('testapp', 'nonexistant', 'default');
		$this->assertEquals('default', $value);
	}

	public function testHasKey() {
		$config = new \OC\AppConfig(\OC::$server->get(Connection::class));

		$this->assertTrue($config->hasKey('testapp', 'installed_version'));
		$this->assertFalse($config->hasKey('testapp', 'nonexistant'));
		$this->assertFalse($config->hasKey('nonexistant', 'nonexistant'));
	}

	public function testSetValueUpdate() {
		$config = new \OC\AppConfig(\OC::$server->get(Connection::class));

		$this->assertEquals('1.2.3', $config->getValue('testapp', 'installed_version'));
		$this->assertConfigKey('testapp', 'installed_version', '1.2.3');

		$wasModified = $config->setValue('testapp', 'installed_version', '1.2.3');
		if (!(\OC::$server->get(Connection::class) instanceof \OC\DB\OracleConnection)) {
			$this->assertFalse($wasModified);
		}

		$this->assertEquals('1.2.3', $config->getValue('testapp', 'installed_version'));
		$this->assertConfigKey('testapp', 'installed_version', '1.2.3');

		$this->assertTrue($config->setValue('testapp', 'installed_version', '1.33.7'));


		$this->assertEquals('1.33.7', $config->getValue('testapp', 'installed_version'));
		$this->assertConfigKey('testapp', 'installed_version', '1.33.7');

		$config->setValue('someapp', 'somekey', 'somevalue');
		$this->assertConfigKey('someapp', 'somekey', 'somevalue');
	}

	public function testSetValueInsert() {
		$config = new \OC\AppConfig(\OC::$server->get(Connection::class));

		$this->assertFalse($config->hasKey('someapp', 'somekey'));
		$this->assertNull($config->getValue('someapp', 'somekey'));

		$this->assertTrue($config->setValue('someapp', 'somekey', 'somevalue'));

		$this->assertTrue($config->hasKey('someapp', 'somekey'));
		$this->assertEquals('somevalue', $config->getValue('someapp', 'somekey'));
		$this->assertConfigKey('someapp', 'somekey', 'somevalue');

		$wasInserted = $config->setValue('someapp', 'somekey', 'somevalue');
		if (!(\OC::$server->get(Connection::class) instanceof \OC\DB\OracleConnection)) {
			$this->assertFalse($wasInserted);
		}
	}

	public function testDeleteKey() {
		$config = new \OC\AppConfig(\OC::$server->get(Connection::class));

		$this->assertTrue($config->hasKey('testapp', 'deletethis'));

		$config->deleteKey('testapp', 'deletethis');

		$this->assertFalse($config->hasKey('testapp', 'deletethis'));

		$sql = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$sql->select('configvalue')
			->from('appconfig')
			->where($sql->expr()->eq('appid', $sql->createParameter('appid')))
			->andWhere($sql->expr()->eq('configkey', $sql->createParameter('configkey')))
			->setParameter('appid', 'testapp')
			->setParameter('configkey', 'deletethis');
		$query = $sql->execute();
		$result = $query->fetch();
		$query->closeCursor();
		$this->assertFalse($result);
	}

	public function testDeleteApp() {
		$config = new \OC\AppConfig(\OC::$server->get(Connection::class));

		$this->assertTrue($config->hasKey('someapp', 'otherkey'));

		$config->deleteApp('someapp');

		$this->assertFalse($config->hasKey('someapp', 'otherkey'));

		$sql = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$sql->select('configvalue')
			->from('appconfig')
			->where($sql->expr()->eq('appid', $sql->createParameter('appid')))
			->setParameter('appid', 'someapp');
		$query = $sql->execute();
		$result = $query->fetch();
		$query->closeCursor();
		$this->assertFalse($result);
	}

	public function testGetValuesNotAllowed() {
		$config = new \OC\AppConfig(\OC::$server->get(Connection::class));

		$this->assertFalse($config->getValues('testapp', 'enabled'));

		$this->assertFalse($config->getValues(false, false));
	}

	public function testGetValues() {
		$config = new \OC\AppConfig(\OC::$server->get(Connection::class));

		$sql = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$sql->select(['configkey', 'configvalue'])
			->from('appconfig')
			->where($sql->expr()->eq('appid', $sql->createParameter('appid')))
			->setParameter('appid', 'testapp');
		$query = $sql->execute();
		$expected = [];
		while ($row = $query->fetch()) {
			$expected[$row['configkey']] = $row['configvalue'];
		}
		$query->closeCursor();

		$values = $config->getValues('testapp', false);
		$this->assertEquals($expected, $values);

		$sql = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$sql->select(['appid', 'configvalue'])
			->from('appconfig')
			->where($sql->expr()->eq('configkey', $sql->createParameter('configkey')))
			->setParameter('configkey', 'enabled');
		$query = $sql->execute();
		$expected = [];
		while ($row = $query->fetch()) {
			$expected[$row['appid']] = $row['configvalue'];
		}
		$query->closeCursor();

		$values = $config->getValues(false, 'enabled');
		$this->assertEquals($expected, $values);
	}

	public function testGetFilteredValues() {
		/** @var \OC\AppConfig|\PHPUnit\Framework\MockObject\MockObject $config */
		$config = $this->getMockBuilder(\OC\AppConfig::class)
			->setConstructorArgs([\OC::$server->get(Connection::class)])
			->setMethods(['getValues'])
			->getMock();

		$config->expects($this->once())
			->method('getValues')
			->with('user_ldap', false)
			->willReturn([
				'ldap_agent_password' => 'secret',
				's42ldap_agent_password' => 'secret',
				'ldap_dn' => 'dn',
			]);

		$values = $config->getFilteredValues('user_ldap');
		$this->assertEquals([
			'ldap_agent_password' => IConfig::SENSITIVE_VALUE,
			's42ldap_agent_password' => IConfig::SENSITIVE_VALUE,
			'ldap_dn' => 'dn',
		], $values);
	}

	public function testSettingConfigParallel() {
		$appConfig1 = new \OC\AppConfig(\OC::$server->get(Connection::class));
		$appConfig2 = new \OC\AppConfig(\OC::$server->get(Connection::class));
		$appConfig1->getValue('testapp', 'foo', 'v1');
		$appConfig2->getValue('testapp', 'foo', 'v1');

		$appConfig1->setValue('testapp', 'foo', 'v1');
		$this->assertConfigKey('testapp', 'foo', 'v1');

		$appConfig2->setValue('testapp', 'foo', 'v2');
		$this->assertConfigKey('testapp', 'foo', 'v2');
	}

	/**
	 * @param string $app
	 * @param string $key
	 * @param string $expected
	 */
	protected function assertConfigKey($app, $key, $expected) {
		$sql = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$sql->select('configvalue')
			->from('appconfig')
			->where($sql->expr()->eq('appid', $sql->createParameter('appid')))
			->andWhere($sql->expr()->eq('configkey', $sql->createParameter('configkey')))
			->setParameter('appid', $app)
			->setParameter('configkey', $key);
		$query = $sql->execute();
		$actual = $query->fetch();
		$query->closeCursor();

		$this->assertEquals($expected, $actual['configvalue']);
	}
}
