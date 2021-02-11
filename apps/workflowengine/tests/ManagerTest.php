<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\WorkflowEngine\Tests;

use OC\L10N\L10N;
use OCA\WorkflowEngine\Entity\File;
use OCA\WorkflowEngine\Helper\ScopeContext;
use OCA\WorkflowEngine\Manager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IServerContainer;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\SystemTag\ISystemTagManager;
use OCP\WorkflowEngine\ICheck;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IEntityEvent;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

/**
 * Class ManagerTest
 *
 * @package OCA\WorkflowEngine\Tests
 * @group DB
 */
class ManagerTest extends TestCase {

	/** @var Manager */
	protected $manager;
	/** @var MockObject|IDBConnection */
	protected $db;
	/** @var \PHPUnit\Framework\MockObject\MockObject|ILogger */
	protected $logger;
	/** @var \PHPUnit\Framework\MockObject\MockObject|EventDispatcherInterface */
	protected $legacyDispatcher;
	/** @var MockObject|IServerContainer */
	protected $container;
	/** @var MockObject|IUserSession */
	protected $session;
	/** @var MockObject|L10N */
	protected $l;
	/** @var MockObject|IEventDispatcher */
	protected $dispatcher;
	/** @var MockObject|IConfig */
	protected $config;

	protected function setUp(): void {
		parent::setUp();

		$this->db = \OC::$server->getDatabaseConnection();
		$this->container = $this->createMock(IServerContainer::class);
		/** @var IL10N|MockObject $l */
		$this->l = $this->createMock(IL10N::class);
		$this->l->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});

		$this->legacyDispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->config = $this->createMock(IConfig::class);

		$this->manager = new Manager(
			\OC::$server->getDatabaseConnection(),
			$this->container,
			$this->l,
			$this->legacyDispatcher,
			$this->logger,
			$this->session,
			$this->dispatcher,
			$this->config
		);
		$this->clearTables();
	}

	protected function tearDown(): void {
		$this->clearTables();
		parent::tearDown();
	}

	/**
	 * @return MockObject|ScopeContext
	 */
	protected function buildScope(string $scopeId = null): MockObject {
		$scopeContext = $this->createMock(ScopeContext::class);
		$scopeContext->expects($this->any())
			->method('getScope')
			->willReturn($scopeId ? IManager::SCOPE_USER : IManager::SCOPE_ADMIN);
		$scopeContext->expects($this->any())
			->method('getScopeId')
			->willReturn($scopeId ?? '');
		$scopeContext->expects($this->any())
			->method('getHash')
			->willReturn(md5($scopeId ?? ''));

		return $scopeContext;
	}

	public function clearTables() {
		$query = $this->db->getQueryBuilder();
		foreach (['flow_checks', 'flow_operations', 'flow_operations_scope'] as $table) {
			$query->delete($table)
				->execute();
		}
	}

	public function testChecks() {
		$check1 = $this->invokePrivate($this->manager, 'addCheck', ['Test', 'equal', 1]);
		$check2 = $this->invokePrivate($this->manager, 'addCheck', ['Test', '!equal', 2]);

		$data = $this->manager->getChecks([$check1]);
		$this->assertArrayHasKey($check1, $data);
		$this->assertArrayNotHasKey($check2, $data);

		$data = $this->manager->getChecks([$check1, $check2]);
		$this->assertArrayHasKey($check1, $data);
		$this->assertArrayHasKey($check2, $data);

		$data = $this->manager->getChecks([$check2, $check1]);
		$this->assertArrayHasKey($check1, $data);
		$this->assertArrayHasKey($check2, $data);

		$data = $this->manager->getChecks([$check2]);
		$this->assertArrayNotHasKey($check1, $data);
		$this->assertArrayHasKey($check2, $data);
	}

	public function testScope() {
		$adminScope = $this->buildScope();
		$userScope = $this->buildScope('jackie');
		$entity = File::class;

		$opId1 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestOp', 'Test01', [11, 22], 'foo', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId1, $adminScope]);

		$opId2 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestOp', 'Test02', [33, 22], 'bar', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId2, $userScope]);
		$opId3 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestOp', 'Test03', [11, 44], 'foobar', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId3, $userScope]);

		$this->assertTrue($this->invokePrivate($this->manager, 'canModify', [$opId1, $adminScope]));
		$this->assertFalse($this->invokePrivate($this->manager, 'canModify', [$opId2, $adminScope]));
		$this->assertFalse($this->invokePrivate($this->manager, 'canModify', [$opId3, $adminScope]));

		$this->assertFalse($this->invokePrivate($this->manager, 'canModify', [$opId1, $userScope]));
		$this->assertTrue($this->invokePrivate($this->manager, 'canModify', [$opId2, $userScope]));
		$this->assertTrue($this->invokePrivate($this->manager, 'canModify', [$opId3, $userScope]));
	}

	public function testGetAllOperations() {
		$adminScope = $this->buildScope();
		$userScope = $this->buildScope('jackie');
		$entity = File::class;

		$opId1 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestAdminOp', 'Test01', [11, 22], 'foo', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId1, $adminScope]);

		$opId2 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestUserOp', 'Test02', [33, 22], 'bar', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId2, $userScope]);
		$opId3 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestUserOp', 'Test03', [11, 44], 'foobar', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId3, $userScope]);

		$adminOps = $this->manager->getAllOperations($adminScope);
		$userOps = $this->manager->getAllOperations($userScope);

		$this->assertSame(1, count($adminOps));
		$this->assertTrue(array_key_exists('OCA\WFE\TestAdminOp', $adminOps));
		$this->assertFalse(array_key_exists('OCA\WFE\TestUserOp', $adminOps));

		$this->assertSame(1, count($userOps));
		$this->assertFalse(array_key_exists('OCA\WFE\TestAdminOp', $userOps));
		$this->assertTrue(array_key_exists('OCA\WFE\TestUserOp', $userOps));
		$this->assertSame(2, count($userOps['OCA\WFE\TestUserOp']));
	}

	public function testGetOperations() {
		$adminScope = $this->buildScope();
		$userScope = $this->buildScope('jackie');
		$entity = File::class;

		$opId1 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestOp', 'Test01', [11, 22], 'foo', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId1, $adminScope]);
		$opId4 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\OtherTestOp', 'Test04', [5], 'foo', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId4, $adminScope]);

		$opId2 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestOp', 'Test02', [33, 22], 'bar', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId2, $userScope]);
		$opId3 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestOp', 'Test03', [11, 44], 'foobar', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId3, $userScope]);
		$opId5 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\OtherTestOp', 'Test05', [5], 'foobar', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId5, $userScope]);

		$adminOps = $this->manager->getOperations('OCA\WFE\TestOp', $adminScope);
		$userOps = $this->manager->getOperations('OCA\WFE\TestOp', $userScope);

		$this->assertSame(1, count($adminOps));
		array_walk($adminOps, function ($op) {
			$this->assertTrue($op['class'] === 'OCA\WFE\TestOp');
		});

		$this->assertSame(2, count($userOps));
		array_walk($userOps, function ($op) {
			$this->assertTrue($op['class'] === 'OCA\WFE\TestOp');
		});
	}

	public function testUpdateOperation() {
		$adminScope = $this->buildScope();
		$userScope = $this->buildScope('jackie');
		$entity = File::class;

		$this->container->expects($this->any())
			->method('query')
			->willReturnCallback(function ($class) {
				if (substr($class, -2) === 'Op') {
					return $this->createMock(IOperation::class);
				} elseif ($class === File::class) {
					return $this->getMockBuilder(File::class)
						->setConstructorArgs([
							$this->l,
							$this->createMock(IURLGenerator::class),
							$this->createMock(IRootFolder::class),
							$this->createMock(ILogger::class),
							$this->createMock(\OCP\Share\IManager::class),
							$this->createMock(IUserSession::class),
							$this->createMock(ISystemTagManager::class),
							$this->createMock(IUserManager::class),
						])
						->setMethodsExcept(['getEvents'])
						->getMock();
				}
				return $this->createMock(ICheck::class);
			});

		$opId1 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestAdminOp', 'Test01', [11, 22], 'foo', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId1, $adminScope]);

		$opId2 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestUserOp', 'Test02', [33, 22], 'bar', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId2, $userScope]);

		$check1 = ['class' => 'OCA\WFE\C22', 'operator' => 'eq', 'value' => 'asdf'];
		$check2 = ['class' => 'OCA\WFE\C33', 'operator' => 'eq', 'value' => 23456];

		/** @noinspection PhpUnhandledExceptionInspection */
		$op = $this->manager->updateOperation($opId1, 'Test01a', [$check1, $check2], 'foohur', $adminScope, $entity, ['\OCP\Files::postDelete']);
		$this->assertSame('Test01a', $op['name']);
		$this->assertSame('foohur', $op['operation']);

		/** @noinspection PhpUnhandledExceptionInspection */
		$op = $this->manager->updateOperation($opId2, 'Test02a', [$check1], 'barfoo', $userScope, $entity, ['\OCP\Files::postDelete']);
		$this->assertSame('Test02a', $op['name']);
		$this->assertSame('barfoo', $op['operation']);

		foreach ([[$adminScope, $opId2], [$userScope, $opId1]] as $run) {
			try {
				/** @noinspection PhpUnhandledExceptionInspection */
				$this->manager->updateOperation($run[1], 'Evil', [$check2], 'hackx0r', $run[0], $entity, []);
				$this->assertTrue(false, 'DomainException not thrown');
			} catch (\DomainException $e) {
				$this->assertTrue(true);
			}
		}
	}

	public function testDeleteOperation() {
		$adminScope = $this->buildScope();
		$userScope = $this->buildScope('jackie');
		$entity = File::class;

		$opId1 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestAdminOp', 'Test01', [11, 22], 'foo', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId1, $adminScope]);

		$opId2 = $this->invokePrivate(
			$this->manager,
			'insertOperation',
			['OCA\WFE\TestUserOp', 'Test02', [33, 22], 'bar', $entity, []]
		);
		$this->invokePrivate($this->manager, 'addScope', [$opId2, $userScope]);

		foreach ([[$adminScope, $opId2], [$userScope, $opId1]] as $run) {
			try {
				/** @noinspection PhpUnhandledExceptionInspection */
				$this->manager->deleteOperation($run[1], $run[0]);
				$this->assertTrue(false, 'DomainException not thrown');
			} catch (\Exception $e) {
				$this->assertInstanceOf(\DomainException::class, $e);
			}
		}

		/** @noinspection PhpUnhandledExceptionInspection */
		$this->manager->deleteOperation($opId1, $adminScope);
		/** @noinspection PhpUnhandledExceptionInspection */
		$this->manager->deleteOperation($opId2, $userScope);

		foreach ([$opId1, $opId2] as $opId) {
			try {
				$this->invokePrivate($this->manager, 'getOperation', [$opId]);
				$this->assertTrue(false, 'UnexpectedValueException not thrown');
			} catch (\Exception $e) {
				$this->assertInstanceOf(\UnexpectedValueException::class, $e);
			}
		}
	}

	public function testGetEntitiesListBuildInOnly() {
		$fileEntityMock = $this->createMock(File::class);

		$this->container->expects($this->once())
			->method('query')
			->with(File::class)
			->willReturn($fileEntityMock);

		$entities = $this->manager->getEntitiesList();

		$this->assertCount(1, $entities);
		$this->assertInstanceOf(IEntity::class, $entities[0]);
	}

	public function testGetEntitiesList() {
		$fileEntityMock = $this->createMock(File::class);

		$this->container->expects($this->once())
			->method('query')
			->with(File::class)
			->willReturn($fileEntityMock);

		/** @var MockObject|IEntity $extraEntity */
		$extraEntity = $this->createMock(IEntity::class);

		$this->legacyDispatcher->expects($this->once())
			->method('dispatch')
			->with('OCP\WorkflowEngine::registerEntities', $this->anything())
			->willReturnCallback(function () use ($extraEntity) {
				$this->manager->registerEntity($extraEntity);
			});

		$entities = $this->manager->getEntitiesList();

		$this->assertCount(2, $entities);

		$entityTypeCounts = array_reduce($entities, function (array $carry, IEntity $entity) {
			if ($entity instanceof File) {
				$carry[0]++;
			} elseif ($entity instanceof IEntity) {
				$carry[1]++;
			}
			return $carry;
		}, [0, 0]);

		$this->assertSame(1, $entityTypeCounts[0]);
		$this->assertSame(1, $entityTypeCounts[1]);
	}

	public function testValidateOperationOK() {
		$check = [
			'class' => ICheck::class,
			'operator' => 'is',
			'value' => 'barfoo',
		];

		$operationMock = $this->createMock(IOperation::class);
		$entityMock = $this->createMock(IEntity::class);
		$eventEntityMock = $this->createMock(IEntityEvent::class);
		$checkMock = $this->createMock(ICheck::class);

		$operationMock->expects($this->once())
			->method('validateOperation')
			->with('test', [$check], 'operationData');

		$entityMock->expects($this->any())
			->method('getEvents')
			->willReturn([$eventEntityMock]);

		$eventEntityMock->expects($this->any())
			->method('getEventName')
			->willReturn('MyEvent');

		$checkMock->expects($this->any())
			->method('supportedEntities')
			->willReturn([IEntity::class]);
		$checkMock->expects($this->atLeastOnce())
			->method('validateCheck');

		$this->container->expects($this->any())
			->method('query')
			->willReturnCallback(function ($className) use ($operationMock, $entityMock, $eventEntityMock, $checkMock) {
				switch ($className) {
					case IOperation::class:
						return $operationMock;
					case IEntity::class:
						return $entityMock;
					case IEntityEvent::class:
						return $eventEntityMock;
					case ICheck::class:
						return $checkMock;
					default:
						return $this->createMock($className);
				}
			});

		$this->manager->validateOperation(IOperation::class, 'test', [$check], 'operationData', IEntity::class, ['MyEvent']);
	}

	public function testValidateOperationCheckInputLengthError() {
		$check = [
			'class' => ICheck::class,
			'operator' => 'is',
			'value' => str_pad('', IManager::MAX_CHECK_VALUE_BYTES + 1, 'FooBar'),
		];

		$operationMock = $this->createMock(IOperation::class);
		$entityMock = $this->createMock(IEntity::class);
		$eventEntityMock = $this->createMock(IEntityEvent::class);
		$checkMock = $this->createMock(ICheck::class);

		$operationMock->expects($this->once())
			->method('validateOperation')
			->with('test', [$check], 'operationData');

		$entityMock->expects($this->any())
			->method('getEvents')
			->willReturn([$eventEntityMock]);

		$eventEntityMock->expects($this->any())
			->method('getEventName')
			->willReturn('MyEvent');

		$checkMock->expects($this->any())
			->method('supportedEntities')
			->willReturn([IEntity::class]);
		$checkMock->expects($this->never())
			->method('validateCheck');

		$this->container->expects($this->any())
			->method('query')
			->willReturnCallback(function ($className) use ($operationMock, $entityMock, $eventEntityMock, $checkMock) {
				switch ($className) {
					case IOperation::class:
						return $operationMock;
					case IEntity::class:
						return $entityMock;
					case IEntityEvent::class:
						return $eventEntityMock;
					case ICheck::class:
						return $checkMock;
					default:
						return $this->createMock($className);
				}
			});

		try {
			$this->manager->validateOperation(IOperation::class, 'test', [$check], 'operationData', IEntity::class, ['MyEvent']);
		} catch (\UnexpectedValueException $e) {
			$this->assertSame('The provided check value is too long', $e->getMessage());
		}
	}

	public function testValidateOperationDataLengthError() {
		$check = [
			'class' => ICheck::class,
			'operator' => 'is',
			'value' => 'barfoo',
		];
		$operationData = str_pad('', IManager::MAX_OPERATION_VALUE_BYTES + 1, 'FooBar');

		$operationMock = $this->createMock(IOperation::class);
		$entityMock = $this->createMock(IEntity::class);
		$eventEntityMock = $this->createMock(IEntityEvent::class);
		$checkMock = $this->createMock(ICheck::class);

		$operationMock->expects($this->never())
			->method('validateOperation');

		$entityMock->expects($this->any())
			->method('getEvents')
			->willReturn([$eventEntityMock]);

		$eventEntityMock->expects($this->any())
			->method('getEventName')
			->willReturn('MyEvent');

		$checkMock->expects($this->any())
			->method('supportedEntities')
			->willReturn([IEntity::class]);
		$checkMock->expects($this->never())
			->method('validateCheck');

		$this->container->expects($this->any())
			->method('query')
			->willReturnCallback(function ($className) use ($operationMock, $entityMock, $eventEntityMock, $checkMock) {
				switch ($className) {
					case IOperation::class:
						return $operationMock;
					case IEntity::class:
						return $entityMock;
					case IEntityEvent::class:
						return $eventEntityMock;
					case ICheck::class:
						return $checkMock;
					default:
						return $this->createMock($className);
				}
			});

		try {
			$this->manager->validateOperation(IOperation::class, 'test', [$check], $operationData, IEntity::class, ['MyEvent']);
		} catch (\UnexpectedValueException $e) {
			$this->assertSame('The provided operation data is too long', $e->getMessage());
		}
	}
}
