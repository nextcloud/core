<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OC\Security\PermissionPolicy;

use OCP\AppFramework\Http\EmptyFeaturePolicy;
use OCP\AppFramework\Http\EmptyPermissionPolicy;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Security\PermissionPolicy\AddPermissionsPolicyEvent;

class PermissionPolicyManager {
	/** @var EmptyPermissionPolicy[] */
	private $policies = [];

	/** @var IEventDispatcher */
	private $dispatcher;

	public function __construct(IEventDispatcher $dispatcher) {
		$this->dispatcher = $dispatcher;
	}

	public function addDefaultPolicy(EmptyPermissionPolicy $policy): void {
		$this->policies[] = $policy;
	}

	public function getDefaultPolicy(): PermissionPolicy {
		$event = new AddPermissionsPolicyEvent($this);
		$this->dispatcher->dispatchTyped($event);

		$defaultPolicy = new PermissionPolicy();
		foreach ($this->policies as $policy) {
			$defaultPolicy = $this->mergePolicies($defaultPolicy, $policy);
		}
		return $defaultPolicy;
	}

	/**
	 * Merges the first given policy with the second one
	 *
	 */
	public function mergePolicies(PermissionPolicy $defaultPolicy,
								  EmptyPermissionPolicy $originalPolicy): PermissionPolicy {
		foreach ((object)(array)$originalPolicy as $name => $value) {
			$setter = 'set' . ucfirst($name);
			if (\is_array($value)) {
				$getter = 'get' . ucfirst($name);
				$currentValues = \is_array($defaultPolicy->$getter()) ? $defaultPolicy->$getter() : [];
				$defaultPolicy->$setter(\array_values(\array_unique(\array_merge($currentValues, $value))));
			} elseif (\is_bool($value)) {
				$defaultPolicy->$setter($value);
			}
		}

		return $defaultPolicy;
	}

	public function mergeFeaturePolicy(PermissionPolicy $defaultPolicy, EmptyFeaturePolicy  $featurePolicy): PermissionPolicy {
		foreach ((object)(array)$featurePolicy as $name => $value) {
			$setter = 'set' . ucfirst($name);
			if (\is_array($value)) {
				$getter = 'get' . ucfirst($name);
				$currentValues = \is_array($defaultPolicy->$getter()) ? $defaultPolicy->$getter() : [];
				$defaultPolicy->$setter(\array_values(\array_unique(\array_merge($currentValues, $value))));
			} elseif (\is_bool($value)) {
				$defaultPolicy->$setter($value);
			}
		}

		return $defaultPolicy;
	}
}
