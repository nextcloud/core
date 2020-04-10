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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Security\CSP;

use OC\Security\CSP\ContentSecurityPolicyManager;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use Test\TestCase;

class AddContentSecurityPolicyEventTest extends TestCase {
	public function testAddEvent() {
		$cspManager = $this->createMock(ContentSecurityPolicyManager::class);
		$policy = $this->createMock(ContentSecurityPolicy::class);
		$event = new AddContentSecurityPolicyEvent($cspManager);

		$cspManager->expects($this->once())
			->method('addDefaultPolicy')
			->with($policy);

		$event->addPolicy($policy);
	}
}
