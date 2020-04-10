<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCP\Security\FeaturePolicy;

use OC\Security\FeaturePolicy\FeaturePolicyManager;
use OCP\AppFramework\Http\EmptyFeaturePolicy;
use OCP\EventDispatcher\Event;

/**
 * @since 17.0.0
 */
class AddFeaturePolicyEvent extends Event {

	/** @var FeaturePolicyManager */
	private $policyManager;

	/**
	 * @since 17.0.0
	 */
	public function __construct(FeaturePolicyManager $policyManager) {
		parent::__construct();
		$this->policyManager = $policyManager;
	}

	/**
	 * @since 17.0.0
	 */
	public function addPolicy(EmptyFeaturePolicy $policy) {
		$this->policyManager->addDefaultPolicy($policy);
	}
}
