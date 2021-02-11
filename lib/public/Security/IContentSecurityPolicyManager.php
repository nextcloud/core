<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP\Security;

use OCP\AppFramework\Http\EmptyContentSecurityPolicy;

/**
 * Used for Content Security Policy manipulations
 *
 * @since 9.0.0
 * @deprecated 17.0.0 listen to the AddContentSecurityPolicyEvent to add a policy
 */
interface IContentSecurityPolicyManager {
	/**
	 * Allows to inject something into the default content policy. This is for
	 * example useful when you're injecting Javascript code into a view belonging
	 * to another controller and cannot modify its Content-Security-Policy itself.
	 * Note that the adjustment is only applied to applications that use AppFramework
	 * controllers.
	 *
	 * To use this from your `app.php` use `\OC::$server->getContentSecurityPolicyManager()->addDefaultPolicy($policy)`,
	 * $policy has to be of type `\OCP\AppFramework\Http\ContentSecurityPolicy`.
	 *
	 * WARNING: Using this API incorrectly may make the instance more insecure.
	 * Do think twice before adding whitelisting resources. Please do also note
	 * that it is not possible to use the `disallowXYZ` functions.
	 *
	 * @param EmptyContentSecurityPolicy $policy
	 * @since 9.0.0
	 * @deprecated 17.0.0 listen to the AddContentSecurityPolicyEvent to add a policy
	 */
	public function addDefaultPolicy(EmptyContentSecurityPolicy $policy);
}
