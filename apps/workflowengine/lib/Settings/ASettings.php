<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\WorkflowEngine\Settings;

use OCA\WorkflowEngine\AppInfo\Application;
use OCA\WorkflowEngine\Manager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\Settings\ISettings;
use OCP\WorkflowEngine\ICheck;
use OCP\WorkflowEngine\IComplexOperation;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IEntityEvent;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\ISpecificOperation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class ASettings implements ISettings {
	/** @var IL10N */
	private $l10n;

	/** @var string */
	private $appName;

	/** @var EventDispatcherInterface */
	private $eventDispatcher;

	/** @var Manager */
	protected $manager;

	/** @var IInitialStateService */
	private $initialStateService;

	/** @var IConfig */
	private $config;

	/**
	 * @param string $appName
	 * @param IL10N $l
	 * @param EventDispatcherInterface $eventDispatcher
	 * @param Manager $manager
	 * @param IInitialStateService $initialStateService
	 * @param IConfig $config
	 */
	public function __construct(
		$appName,
		IL10N $l,
		EventDispatcherInterface $eventDispatcher,
		Manager $manager,
		IInitialStateService $initialStateService,
		IConfig $config
	) {
		$this->appName = $appName;
		$this->l10n = $l;
		$this->eventDispatcher = $eventDispatcher;
		$this->manager = $manager;
		$this->initialStateService = $initialStateService;
		$this->config = $config;
	}

	abstract function getScope(): int;

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$this->eventDispatcher->dispatch('OCP\WorkflowEngine::loadAdditionalSettingScripts');

		$entities = $this->manager->getEntitiesList();
		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'entities',
			$this->entitiesToArray($entities)
		);

		$operators = $this->manager->getOperatorList();
		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'operators',
			$this->operatorsToArray($operators)
		);

		$checks = $this->manager->getCheckList();
		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'checks',
			$this->checksToArray($checks)
		);

		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'scope',
			$this->getScope()
		);

		$this->initialStateService->provideInitialState(
			Application::APP_ID,
			'appstoreenabled',
			$this->config->getSystemValueBool('appstoreenabled', true)
		);

		return new TemplateResponse(Application::APP_ID, 'settings', [], 'blank');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		return 'workflow';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority() {
		return 0;
	}

	private function entitiesToArray(array $entities) {
		return array_map(function (IEntity $entity) {
			$events = array_map(function (IEntityEvent $entityEvent) {
				return [
					'eventName' => $entityEvent->getEventName(),
					'displayName' => $entityEvent->getDisplayName()
				];
			}, $entity->getEvents());

			return [
				'id' => get_class($entity),
				'icon' => $entity->getIcon(),
				'name' => $entity->getName(),
				'events' => $events,
			];
		}, $entities);
	}

	private function operatorsToArray(array $operators) {
		$operators = array_filter($operators, function (IOperation $operator) {
			return $operator->isAvailableForScope($this->getScope());
		});

		return array_map(function (IOperation $operator) {
			return [
				'id' => get_class($operator),
				'icon' => $operator->getIcon(),
				'name' => $operator->getDisplayName(),
				'description' => $operator->getDescription(),
				'fixedEntity' => $operator instanceof ISpecificOperation ? $operator->getEntityId() : '',
				'isComplex' => $operator instanceof IComplexOperation,
				'triggerHint' => $operator instanceof IComplexOperation ? $operator->getTriggerHint() : '',
			];
		}, $operators);
	}

	private function checksToArray(array $checks) {
		$checks = array_filter($checks, function (ICheck $check) {
			return $check->isAvailableForScope($this->getScope());
		});

		return array_map(function (ICheck $check) {
			return [
				'id' => get_class($check),
				'supportedEntities' => $check->supportedEntities(),
			];
		}, $checks);
	}
}
