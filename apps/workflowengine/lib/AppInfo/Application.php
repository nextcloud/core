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

namespace OCA\WorkflowEngine\AppInfo;

use OCA\WorkflowEngine\Controller\RequestTime;
use OCA\WorkflowEngine\Helper\LogContext;
use OCA\WorkflowEngine\Manager;
use OCA\WorkflowEngine\Service\Logger;
use OCP\AppFramework\QueryException;
use OCP\EventDispatcher\Event;
use OCP\Template;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IEntityCompat;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IOperationCompat;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Application extends \OCP\AppFramework\App {

	const APP_ID = 'workflowengine';

	/** @var EventDispatcherInterface */
	protected $dispatcher;
	/** @var Manager */
	protected $manager;

	public function __construct() {
		parent::__construct(self::APP_ID);

		$this->getContainer()->registerAlias('RequestTimeController', RequestTime::class);

		$this->dispatcher = $this->getContainer()->getServer()->getEventDispatcher();
		$this->manager = $this->getContainer()->query(Manager::class);
	}

	/**
	 * Register all hooks and listeners
	 */
	public function registerHooksAndListeners() {
		$this->dispatcher->addListener(
			'OCP\WorkflowEngine::loadAdditionalSettingScripts',
			function () {
				if (!function_exists('style')) {
					// This is hacky, but we need to load the template class
					class_exists(Template::class, true);
				}

				script('core', [
					'files/fileinfo',
					'files/client',
					'systemtags/systemtags',
					'systemtags/systemtagmodel',
					'systemtags/systemtagscollection',
				]);

				script(self::APP_ID, [
					'workflowengine',
				]);
			},
			-100
		);
	}

	public function registerRuleListeners() {
		$configuredEvents = $this->manager->getAllConfiguredEvents();

		foreach ($configuredEvents as $operationClass => $events) {
			foreach ($events as $entityClass => $eventNames) {
				array_map(function (string $eventName) use ($operationClass, $entityClass) {
					$this->dispatcher->addListener(
						$eventName,
						function ($event) use ($eventName, $operationClass, $entityClass) {
							$ruleMatcher = $this->manager->getRuleMatcher();
							try {
								/** @var IEntity $entity */
								$entity = $this->getContainer()->query($entityClass);
								/** @var IOperation $operation */
								$operation = $this->getContainer()->query($operationClass);

								$ruleMatcher->setEntity($entity);
								$ruleMatcher->setOperation($operation);

								$ctx = new LogContext();
								$ctx
									->setOperation($operation)
									->setEntity($entity)
									->setEventName($eventName);

								/** @var Logger $flowLogger */
								$flowLogger = $this->getContainer()->query(Logger::class);
								$flowLogger->logEventInit($ctx);

								if ($event instanceof Event) {
									$entity->prepareRuleMatcher($ruleMatcher, $eventName, $event);
									$operation->onEvent($eventName, $event, $ruleMatcher);
								} else if ($entity instanceof IEntityCompat && $operation instanceof IOperationCompat) {
									// TODO: Remove this block (and the compat classes) in the first major release in 2023
									$entity->prepareRuleMatcherCompat($ruleMatcher, $eventName, $event);
									$operation->onEventCompat($eventName, $event, $ruleMatcher);
								} else {
									$logger = $this->getContainer()->getServer()->getLogger();
									$logger->debug(
										'Cannot handle event {name} of {event} against entity {entity} and operation {operation}',
										[
											'app' => self::APP_ID,
											'name' => $eventName,
											'event' => get_class($event),
											'entity' => $entityClass,
											'operation' => $operationClass,
										]
									);
								}
								$flowLogger->logEventDone($ctx);

							} catch (QueryException $e) {
								// Ignore query exceptions since they might occur when an entity/operation were setup before by an app that is disabled now
							}
						}
					);
				}, $eventNames ?? []);
			}
		}


	}
}
