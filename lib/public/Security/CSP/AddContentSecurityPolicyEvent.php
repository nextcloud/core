<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP\Security\CSP;

use OC\Security\CSP\ContentSecurityPolicyManager;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\EventDispatcher\Event;

/**
 * Allows to inject something into the default content policy. This is for
 * example useful when you're injecting Javascript code into a view belonging
 * to another controller and cannot modify its Content-Security-Policy itself.
 * Note that the adjustment is only applied to applications that use AppFramework
 * controllers.
 *
 * WARNING: Using this API incorrectly may make the instance more insecure.
 * Do think twice before adding whitelisting resources. Please do also note
 * that it is not possible to use the `disallowXYZ` functions.
 *
 * @since 17.0.0
 */
class AddContentSecurityPolicyEvent extends Event {

	/** @var ContentSecurityPolicyManager */
	private $policyManager;

	/**
	 * @since 17.0.0
	 */
	public function __construct(ContentSecurityPolicyManager $policyManager) {
		parent::__construct();
		$this->policyManager = $policyManager;
	}

	/**
	 * @since 17.0.0
	 */
	public function addPolicy(EmptyContentSecurityPolicy $csp): void {
		$this->policyManager->addDefaultPolicy($csp);
	}
}
