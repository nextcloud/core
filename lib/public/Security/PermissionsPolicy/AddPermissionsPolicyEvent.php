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

namespace OCP\Security\PermissionPolicy;

use OC\Security\PermissionPolicy\PermissionPolicyManager;
use OCP\AppFramework\Http\EmptyPermissionPolicy;
use OCP\EventDispatcher\Event;

/**
 * Event that allows to register a feature policy header to a request.
 *
 * @since 21.0.0
 */
class AddPermissionsPolicyEvent extends Event {

	/** @var PermissionPolicyManager */
	private $policyManager;

	/**
	 * @since 21.0.0
	 */
	public function __construct(PermissionPolicyManager $policyManager) {
		parent::__construct();
		$this->policyManager = $policyManager;
	}

	/**
	 * @since 21.0.0
	 */
	public function addPolicy(EmptyPermissionPolicy $policy) {
		$this->policyManager->addDefaultPolicy($policy);
	}
}
