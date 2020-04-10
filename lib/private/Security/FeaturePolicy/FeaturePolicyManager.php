<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Security\FeaturePolicy;

use OCP\AppFramework\Http\EmptyFeaturePolicy;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Security\FeaturePolicy\AddFeaturePolicyEvent;

class FeaturePolicyManager {
	/** @var EmptyFeaturePolicy[] */
	private $policies = [];

	/** @var IEventDispatcher */
	private $dispatcher;

	public function __construct(IEventDispatcher $dispatcher) {
		$this->dispatcher = $dispatcher;
	}

	public function addDefaultPolicy(EmptyFeaturePolicy $policy): void {
		$this->policies[] = $policy;
	}

	public function getDefaultPolicy(): FeaturePolicy {
		$event = new AddFeaturePolicyEvent($this);
		$this->dispatcher->dispatch(AddFeaturePolicyEvent::class, $event);

		$defaultPolicy = new FeaturePolicy();
		foreach ($this->policies as $policy) {
			$defaultPolicy = $this->mergePolicies($defaultPolicy, $policy);
		}
		return $defaultPolicy;
	}

	/**
	 * Merges the first given policy with the second one
	 *
	 */
	public function mergePolicies(FeaturePolicy $defaultPolicy,
								  EmptyFeaturePolicy $originalPolicy): FeaturePolicy {
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
}
