<?php
/**
 * @copyright Copyright (c) 2016 Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\WorkflowEngine;

use Doctrine\DBAL\Exception;
use OC\Cache\CappedMemoryCache;
use OCA\WorkflowEngine\AppInfo\Application;
use OCA\WorkflowEngine\Check\FileMimeType;
use OCA\WorkflowEngine\Check\FileName;
use OCA\WorkflowEngine\Check\FileSize;
use OCA\WorkflowEngine\Check\FileSystemTags;
use OCA\WorkflowEngine\Check\RequestRemoteAddress;
use OCA\WorkflowEngine\Check\RequestTime;
use OCA\WorkflowEngine\Check\RequestURL;
use OCA\WorkflowEngine\Check\RequestUserAgent;
use OCA\WorkflowEngine\Check\UserGroupMembership;
use OCA\WorkflowEngine\Entity\File;
use OCA\WorkflowEngine\Helper\ScopeContext;
use OCA\WorkflowEngine\Service\Logger;
use OCA\WorkflowEngine\Service\RuleMatcher;
use OCP\AppFramework\QueryException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Storage\IStorage;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IServerContainer;
use OCP\IUserSession;
use OCP\WorkflowEngine\Events\RegisterChecksEvent;
use OCP\WorkflowEngine\Events\RegisterEntitiesEvent;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;
use OCP\WorkflowEngine\ICheck;
use OCP\WorkflowEngine\IComplexOperation;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IEntityEvent;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as LegacyDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;

class Manager implements IManager {

	/** @var IStorage */
	protected $storage;

	/** @var string */
	protected $path;

	/** @var object */
	protected $entity;

	/** @var array[] */
	protected $operations = [];

	/** @var array[] */
	protected $checks = [];

	/** @var IDBConnection */
	protected $connection;

	/** @var IServerContainer|\OC\Server */
	protected $container;

	/** @var IL10N */
	protected $l;

	/** @var LegacyDispatcher */
	protected $legacyEventDispatcher;

	/** @var IEntity[] */
	protected $registeredEntities = [];

	/** @var IOperation[] */
	protected $registeredOperators = [];

	/** @var ICheck[] */
	protected $registeredChecks = [];

	/** @var ILogger */
	protected $logger;

	/** @var CappedMemoryCache */
	protected $operationsByScope = [];

	/** @var IUserSession */
	protected $session;

	/** @var IEventDispatcher */
	private $dispatcher;

	/** @var IConfig */
	private $config;

	public function __construct(
		IDBConnection $connection,
		IServerContainer $container,
		IL10N $l,
		LegacyDispatcher $eventDispatcher,
		ILogger $logger,
		IUserSession $session,
		IEventDispatcher $dispatcher,
		IConfig $config
	) {
		$this->connection = $connection;
		$this->container = $container;
		$this->l = $l;
		$this->legacyEventDispatcher = $eventDispatcher;
		$this->logger = $logger;
		$this->operationsByScope = new CappedMemoryCache(64);
		$this->session = $session;
		$this->dispatcher = $dispatcher;
		$this->config = $config;
	}

	public function getRuleMatcher(): IRuleMatcher {
		return new RuleMatcher(
			$this->session,
			$this->container,
			$this->l,
			$this,
			$this->container->query(Logger::class)
		);
	}

	public function getAllConfiguredEvents() {
		$query = $this->connection->getQueryBuilder();

		$query->select('class', 'entity', $query->expr()->castColumn('events', IQueryBuilder::PARAM_STR))
			->from('flow_operations')
			->where($query->expr()->neq('events', $query->createNamedParameter('[]'), IQueryBuilder::PARAM_STR))
			->groupBy('class', 'entity', $query->expr()->castColumn('events', IQueryBuilder::PARAM_STR));

		$result = $query->execute();
		$operations = [];
		while ($row = $result->fetch()) {
			$eventNames = \json_decode($row['events']);

			$operation = $row['class'];
			$entity = $row['entity'];

			$operations[$operation] = $operations[$row['class']] ?? [];
			$operations[$operation][$entity] = $operations[$operation][$entity] ?? [];

			$operations[$operation][$entity] = array_unique(array_merge($operations[$operation][$entity], $eventNames ?? []));
		}
		$result->closeCursor();

		return $operations;
	}

	/**
	 * @param string $operationClass
	 * @return ScopeContext[]
	 */
	public function getAllConfiguredScopesForOperation(string $operationClass): array {
		static $scopesByOperation = [];
		if (isset($scopesByOperation[$operationClass])) {
			return $scopesByOperation[$operationClass];
		}

		$query = $this->connection->getQueryBuilder();

		$query->selectDistinct('s.type')
			->addSelect('s.value')
			->from('flow_operations', 'o')
			->leftJoin('o', 'flow_operations_scope', 's', $query->expr()->eq('o.id', 's.operation_id'))
			->where($query->expr()->eq('o.class', $query->createParameter('operationClass')));

		$query->setParameters(['operationClass' => $operationClass]);
		$result = $query->execute();

		$scopesByOperation[$operationClass] = [];
		while ($row = $result->fetch()) {
			$scope = new ScopeContext($row['type'], $row['value']);
			$scopesByOperation[$operationClass][$scope->getHash()] = $scope;
		}

		return $scopesByOperation[$operationClass];
	}

	public function getAllOperations(ScopeContext $scopeContext): array {
		if (isset($this->operations[$scopeContext->getHash()])) {
			return $this->operations[$scopeContext->getHash()];
		}

		$query = $this->connection->getQueryBuilder();

		$query->select('o.*')
			->selectAlias('s.type', 'scope_type')
			->selectAlias('s.value', 'scope_actor_id')
			->from('flow_operations', 'o')
			->leftJoin('o', 'flow_operations_scope', 's', $query->expr()->eq('o.id', 's.operation_id'))
			->where($query->expr()->eq('s.type', $query->createParameter('scope')));

		if ($scopeContext->getScope() === IManager::SCOPE_USER) {
			$query->andWhere($query->expr()->eq('s.value', $query->createParameter('scopeId')));
		}

		$query->setParameters(['scope' => $scopeContext->getScope(), 'scopeId' => $scopeContext->getScopeId()]);
		$result = $query->execute();

		$this->operations[$scopeContext->getHash()] = [];
		while ($row = $result->fetch()) {
			if (!isset($this->operations[$scopeContext->getHash()][$row['class']])) {
				$this->operations[$scopeContext->getHash()][$row['class']] = [];
			}
			$this->operations[$scopeContext->getHash()][$row['class']][] = $row;
		}

		return $this->operations[$scopeContext->getHash()];
	}

	public function getOperations(string $class, ScopeContext $scopeContext): array {
		if (!isset($this->operations[$scopeContext->getHash()])) {
			$this->getAllOperations($scopeContext);
		}
		return $this->operations[$scopeContext->getHash()][$class] ?? [];
	}

	/**
	 * @param int $id
	 * @return array
	 * @throws \UnexpectedValueException
	 */
	protected function getOperation($id) {
		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('flow_operations')
			->where($query->expr()->eq('id', $query->createNamedParameter($id)));
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row) {
			return $row;
		}

		throw new \UnexpectedValueException($this->l->t('Operation #%s does not exist', [$id]));
	}

	protected function insertOperation(
		string $class,
		string $name,
		array $checkIds,
		string $operation,
		string $entity,
		array $events
	): int {
		$query = $this->connection->getQueryBuilder();
		$query->insert('flow_operations')
			->values([
				'class' => $query->createNamedParameter($class),
				'name' => $query->createNamedParameter($name),
				'checks' => $query->createNamedParameter(json_encode(array_unique($checkIds))),
				'operation' => $query->createNamedParameter($operation),
				'entity' => $query->createNamedParameter($entity),
				'events' => $query->createNamedParameter(json_encode($events))
			]);
		$query->execute();

		return $query->getLastInsertId();
	}

	/**
	 * @param string $class
	 * @param string $name
	 * @param array[] $checks
	 * @param string $operation
	 * @return array The added operation
	 * @throws \UnexpectedValueException
	 * @throw Exception
	 */
	public function addOperation(
		string $class,
		string $name,
		array $checks,
		string $operation,
		ScopeContext $scope,
		string $entity,
		array $events
	) {
		$this->validateOperation($class, $name, $checks, $operation, $entity, $events);

		$this->connection->beginTransaction();

		try {
			$checkIds = [];
			foreach ($checks as $check) {
				$checkIds[] = $this->addCheck($check['class'], $check['operator'], $check['value']);
			}

			$id = $this->insertOperation($class, $name, $checkIds, $operation, $entity, $events);
			$this->addScope($id, $scope);

			$this->connection->commit();
		} catch (Exception $e) {
			$this->connection->rollBack();
			throw $e;
		}

		return $this->getOperation($id);
	}

	protected function canModify(int $id, ScopeContext $scopeContext):bool {
		if (isset($this->operationsByScope[$scopeContext->getHash()])) {
			return in_array($id, $this->operationsByScope[$scopeContext->getHash()], true);
		}

		$qb = $this->connection->getQueryBuilder();
		$qb = $qb->select('o.id')
			->from('flow_operations', 'o')
			->leftJoin('o', 'flow_operations_scope', 's', $qb->expr()->eq('o.id', 's.operation_id'))
			->where($qb->expr()->eq('s.type', $qb->createParameter('scope')));

		if ($scopeContext->getScope() !== IManager::SCOPE_ADMIN) {
			$qb->where($qb->expr()->eq('s.value', $qb->createParameter('scopeId')));
		}

		$qb->setParameters(['scope' => $scopeContext->getScope(), 'scopeId' => $scopeContext->getScopeId()]);
		$result = $qb->execute();

		$this->operationsByScope[$scopeContext->getHash()] = [];
		while ($opId = $result->fetchOne()) {
			$this->operationsByScope[$scopeContext->getHash()][] = (int)$opId;
		}
		$result->closeCursor();

		return in_array($id, $this->operationsByScope[$scopeContext->getHash()], true);
	}

	/**
	 * @param int $id
	 * @param string $name
	 * @param array[] $checks
	 * @param string $operation
	 * @return array The updated operation
	 * @throws \UnexpectedValueException
	 * @throws \DomainException
	 * @throws Exception
	 */
	public function updateOperation(
		int $id,
		string $name,
		array $checks,
		string $operation,
		ScopeContext $scopeContext,
		string $entity,
		array $events
	): array {
		if (!$this->canModify($id, $scopeContext)) {
			throw new \DomainException('Target operation not within scope');
		};
		$row = $this->getOperation($id);
		$this->validateOperation($row['class'], $name, $checks, $operation, $entity, $events);

		$checkIds = [];
		try {
			$this->connection->beginTransaction();
			foreach ($checks as $check) {
				$checkIds[] = $this->addCheck($check['class'], $check['operator'], $check['value']);
			}

			$query = $this->connection->getQueryBuilder();
			$query->update('flow_operations')
				->set('name', $query->createNamedParameter($name))
				->set('checks', $query->createNamedParameter(json_encode(array_unique($checkIds))))
				->set('operation', $query->createNamedParameter($operation))
				->set('entity', $query->createNamedParameter($entity))
				->set('events', $query->createNamedParameter(json_encode($events)))
				->where($query->expr()->eq('id', $query->createNamedParameter($id)));
			$query->execute();
			$this->connection->commit();
		} catch (Exception $e) {
			$this->connection->rollBack();
			throw $e;
		}
		unset($this->operations[$scopeContext->getHash()]);

		return $this->getOperation($id);
	}

	/**
	 * @param int $id
	 * @return bool
	 * @throws \UnexpectedValueException
	 * @throws Exception
	 * @throws \DomainException
	 */
	public function deleteOperation($id, ScopeContext $scopeContext) {
		if (!$this->canModify($id, $scopeContext)) {
			throw new \DomainException('Target operation not within scope');
		};
		$query = $this->connection->getQueryBuilder();
		try {
			$this->connection->beginTransaction();
			$result = (bool)$query->delete('flow_operations')
				->where($query->expr()->eq('id', $query->createNamedParameter($id)))
				->execute();
			if ($result) {
				$qb = $this->connection->getQueryBuilder();
				$result &= (bool)$qb->delete('flow_operations_scope')
					->where($qb->expr()->eq('operation_id', $qb->createNamedParameter($id)))
					->execute();
			}
			$this->connection->commit();
		} catch (Exception $e) {
			$this->connection->rollBack();
			throw $e;
		}

		if (isset($this->operations[$scopeContext->getHash()])) {
			unset($this->operations[$scopeContext->getHash()]);
		}

		return $result;
	}

	protected function validateEvents(string $entity, array $events, IOperation $operation) {
		try {
			/** @var IEntity $instance */
			$instance = $this->container->query($entity);
		} catch (QueryException $e) {
			throw new \UnexpectedValueException($this->l->t('Entity %s does not exist', [$entity]));
		}

		if (!$instance instanceof IEntity) {
			throw new \UnexpectedValueException($this->l->t('Entity %s is invalid', [$entity]));
		}

		if (empty($events)) {
			if (!$operation instanceof IComplexOperation) {
				throw new \UnexpectedValueException($this->l->t('No events are chosen.'));
			}
			return;
		}

		$availableEvents = [];
		foreach ($instance->getEvents() as $event) {
			/** @var IEntityEvent $event */
			$availableEvents[] = $event->getEventName();
		}

		$diff = array_diff($events, $availableEvents);
		if (!empty($diff)) {
			throw new \UnexpectedValueException($this->l->t('Entity %s has no event %s', [$entity, array_shift($diff)]));
		}
	}

	/**
	 * @param string $class
	 * @param string $name
	 * @param array[] $checks
	 * @param string $operation
	 * @throws \UnexpectedValueException
	 */
	public function validateOperation($class, $name, array $checks, $operation, string $entity, array $events) {
		try {
			/** @var IOperation $instance */
			$instance = $this->container->query($class);
		} catch (QueryException $e) {
			throw new \UnexpectedValueException($this->l->t('Operation %s does not exist', [$class]));
		}

		if (!($instance instanceof IOperation)) {
			throw new \UnexpectedValueException($this->l->t('Operation %s is invalid', [$class]));
		}

		$this->validateEvents($entity, $events, $instance);

		if (count($checks) === 0) {
			throw new \UnexpectedValueException($this->l->t('At least one check needs to be provided'));
		}

		if (strlen((string)$operation) > IManager::MAX_OPERATION_VALUE_BYTES) {
			throw new \UnexpectedValueException($this->l->t('The provided operation data is too long'));
		}

		$instance->validateOperation($name, $checks, $operation);

		foreach ($checks as $check) {
			if (!is_string($check['class'])) {
				throw new \UnexpectedValueException($this->l->t('Invalid check provided'));
			}

			try {
				/** @var ICheck $instance */
				$instance = $this->container->query($check['class']);
			} catch (QueryException $e) {
				throw new \UnexpectedValueException($this->l->t('Check %s does not exist', [$class]));
			}

			if (!($instance instanceof ICheck)) {
				throw new \UnexpectedValueException($this->l->t('Check %s is invalid', [$class]));
			}

			if (!empty($instance->supportedEntities())
				&& !in_array($entity, $instance->supportedEntities())
			) {
				throw new \UnexpectedValueException($this->l->t('Check %s is not allowed with this entity', [$class]));
			}

			if (strlen((string)$check['value']) > IManager::MAX_CHECK_VALUE_BYTES) {
				throw new \UnexpectedValueException($this->l->t('The provided check value is too long'));
			}

			$instance->validateCheck($check['operator'], $check['value']);
		}
	}

	/**
	 * @param int[] $checkIds
	 * @return array[]
	 */
	public function getChecks(array $checkIds) {
		$checkIds = array_map('intval', $checkIds);

		$checks = [];
		foreach ($checkIds as $i => $checkId) {
			if (isset($this->checks[$checkId])) {
				$checks[$checkId] = $this->checks[$checkId];
				unset($checkIds[$i]);
			}
		}

		if (empty($checkIds)) {
			return $checks;
		}

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('flow_checks')
			->where($query->expr()->in('id', $query->createNamedParameter($checkIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$result = $query->execute();

		while ($row = $result->fetch()) {
			$this->checks[(int) $row['id']] = $row;
			$checks[(int) $row['id']] = $row;
		}
		$result->closeCursor();

		$checkIds = array_diff($checkIds, array_keys($checks));

		if (!empty($checkIds)) {
			$missingCheck = array_pop($checkIds);
			throw new \UnexpectedValueException($this->l->t('Check #%s does not exist', $missingCheck));
		}

		return $checks;
	}

	/**
	 * @param string $class
	 * @param string $operator
	 * @param string $value
	 * @return int Check unique ID
	 */
	protected function addCheck($class, $operator, $value) {
		$hash = md5($class . '::' . $operator . '::' . $value);

		$query = $this->connection->getQueryBuilder();
		$query->select('id')
			->from('flow_checks')
			->where($query->expr()->eq('hash', $query->createNamedParameter($hash)));
		$result = $query->execute();

		if ($row = $result->fetch()) {
			$result->closeCursor();
			return (int) $row['id'];
		}

		$query = $this->connection->getQueryBuilder();
		$query->insert('flow_checks')
			->values([
				'class' => $query->createNamedParameter($class),
				'operator' => $query->createNamedParameter($operator),
				'value' => $query->createNamedParameter($value),
				'hash' => $query->createNamedParameter($hash),
			]);
		$query->execute();

		return $query->getLastInsertId();
	}

	protected function addScope(int $operationId, ScopeContext $scope): void {
		$query = $this->connection->getQueryBuilder();

		$insertQuery = $query->insert('flow_operations_scope');
		$insertQuery->values([
			'operation_id' => $query->createNamedParameter($operationId),
			'type' => $query->createNamedParameter($scope->getScope()),
			'value' => $query->createNamedParameter($scope->getScopeId()),
		]);
		$insertQuery->execute();
	}

	public function formatOperation(array $operation): array {
		$checkIds = json_decode($operation['checks'], true);
		$checks = $this->getChecks($checkIds);

		$operation['checks'] = [];
		foreach ($checks as $check) {
			// Remove internal values
			unset($check['id']);
			unset($check['hash']);

			$operation['checks'][] = $check;
		}
		$operation['events'] = json_decode($operation['events'], true) ?? [];


		return $operation;
	}

	/**
	 * @return IEntity[]
	 */
	public function getEntitiesList(): array {
		$this->dispatcher->dispatchTyped(new RegisterEntitiesEvent($this));
		$this->legacyEventDispatcher->dispatch(IManager::EVENT_NAME_REG_ENTITY, new GenericEvent($this));

		return array_values(array_merge($this->getBuildInEntities(), $this->registeredEntities));
	}

	/**
	 * @return IOperation[]
	 */
	public function getOperatorList(): array {
		$this->dispatcher->dispatchTyped(new RegisterOperationsEvent($this));
		$this->legacyEventDispatcher->dispatch(IManager::EVENT_NAME_REG_OPERATION, new GenericEvent($this));

		return array_merge($this->getBuildInOperators(), $this->registeredOperators);
	}

	/**
	 * @return ICheck[]
	 */
	public function getCheckList(): array {
		$this->dispatcher->dispatchTyped(new RegisterChecksEvent($this));
		$this->legacyEventDispatcher->dispatch(IManager::EVENT_NAME_REG_CHECK, new GenericEvent($this));

		return array_merge($this->getBuildInChecks(), $this->registeredChecks);
	}

	public function registerEntity(IEntity $entity): void {
		$this->registeredEntities[get_class($entity)] = $entity;
	}

	public function registerOperation(IOperation $operator): void {
		$this->registeredOperators[get_class($operator)] = $operator;
	}

	public function registerCheck(ICheck $check): void {
		$this->registeredChecks[get_class($check)] = $check;
	}

	/**
	 * @return IEntity[]
	 */
	protected function getBuildInEntities(): array {
		try {
			return [
				File::class => $this->container->query(File::class),
			];
		} catch (QueryException $e) {
			$this->logger->logException($e);
			return [];
		}
	}

	/**
	 * @return IOperation[]
	 */
	protected function getBuildInOperators(): array {
		try {
			return [
				// None yet
			];
		} catch (QueryException $e) {
			$this->logger->logException($e);
			return [];
		}
	}

	/**
	 * @return ICheck[]
	 */
	protected function getBuildInChecks(): array {
		try {
			return [
				$this->container->query(FileMimeType::class),
				$this->container->query(FileName::class),
				$this->container->query(FileSize::class),
				$this->container->query(FileSystemTags::class),
				$this->container->query(RequestRemoteAddress::class),
				$this->container->query(RequestTime::class),
				$this->container->query(RequestURL::class),
				$this->container->query(RequestUserAgent::class),
				$this->container->query(UserGroupMembership::class),
			];
		} catch (QueryException $e) {
			$this->logger->logException($e);
			return [];
		}
	}

	public function isUserScopeEnabled(): bool {
		return $this->config->getAppValue(Application::APP_ID, 'user_scope_disabled', 'no') === 'no';
	}
}
