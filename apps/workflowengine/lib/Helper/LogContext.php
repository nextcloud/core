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

namespace OCA\WorkflowEngine\Helper;

use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;

class LogContext {
	/** @var array */
	protected $details;

	public function setDescription(string $description): LogContext {
		$this->details['message'] = $description;
		return $this;
	}

	public function setScopes(array $scopes): LogContext {
		$this->details['scopes'] = [];
		foreach ($scopes as $scope) {
			if($scope instanceof ScopeContext) {
				switch($scope->getScope()) {
					case IManager::SCOPE_ADMIN:
						$this->details['scopes'][] = ['scope' => 'admin'];
						break;
					case IManager::SCOPE_USER:
						$this->details['scopes'][] = [
							'scope' => 'user',
							'uid' => $scope->getScopeId(),
						];
						break;
					default:
						continue 2;
				}
			}
		}
		return $this;
	}

	public function setOperation(?IOperation $operation): LogContext {
		if($operation instanceof IOperation) {
			$this->details['operation'] = [
				'class' => get_class($operation),
				'name' => $operation->getDisplayName(),
			];
		}
		return $this;
	}

	public function setEntity(?IEntity $entity): LogContext {
		if($entity instanceof IEntity) {
			$this->details['entity'] = [
				'class' => get_class($entity),
				'name' => $entity->getName(),
			];
		}
		return $this;
	}

	public function setConfiguration(array $configuration): LogContext {
		$this->details['configuration'] = $configuration;
		return $this;
	}

	public function setEventName(string $eventName): LogContext {
		$this->details['eventName'] = $eventName;
		return $this;
	}

	public function getDetails(): array {
		return $this->details;
	}
}
