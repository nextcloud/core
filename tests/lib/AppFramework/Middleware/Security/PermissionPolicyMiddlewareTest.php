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

namespace Test\AppFramework\Middleware\Security;

use OC\AppFramework\Middleware\Security\PermissionPolicyMiddleware;
use OC\Security\FeaturePolicy\FeaturePolicy;
use OC\Security\FeaturePolicy\FeaturePolicyManager;
use OC\Security\PermissionPolicy\PermissionPolicy;
use OC\Security\PermissionPolicy\PermissionPolicyManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\EmptyFeaturePolicy;
use OCP\AppFramework\Http\EmptyPermissionPolicy;
use OCP\AppFramework\Http\Response;
use PHPUnit\Framework\MockObject\MockObject;

class PermissionPolicyMiddlewareTest extends \Test\TestCase {

	/** @var PermissionPolicyMiddleware|MockObject */
	private $middleware;
	/** @var Controller|MockObject */
	private $controller;
	/** @var FeaturePolicyManager|MockObject */
	private $featurePolicyManager;
	/** @var PermissionPolicyManager|MockObject */
	private $permissionPolicyManager;

	protected function setUp(): void {
		parent::setUp();

		$this->controller = $this->createMock(Controller::class);
		$this->featurePolicyManager = $this->createMock(FeaturePolicyManager::class);
		$this->permissionPolicyManager = $this->createMock(PermissionPolicyManager::class);
		$this->middleware = new PermissionPolicyMiddleware(
			$this->featurePolicyManager,
			$this->permissionPolicyManager
		);
	}

	public function testAfterController() {
		$response = $this->createMock(Response::class);
		$defaultPolicy = new FeaturePolicy();
		$defaultPolicy->addAllowedCameraDomain('defaultpolicy');
		$currentPolicy = new FeaturePolicy();
		$currentPolicy->addAllowedAutoplayDomain('currentPolicy');
		$mergedPolicy = new FeaturePolicy();
		$mergedPolicy->addAllowedGeoLocationDomain('mergedPolicy');
		$response->method('getFeaturePolicy')
			->willReturn($currentPolicy);
		$this->featurePolicyManager->method('getDefaultPolicy')
			->willReturn($defaultPolicy);
		$this->featurePolicyManager->method('mergePolicies')
			->with($defaultPolicy, $currentPolicy)
			->willReturn($mergedPolicy);
		$response->expects($this->once())
			->method('setFeaturePolicy')
			->with($mergedPolicy);

		$defaultPermissionPolicy = new PermissionPolicy();
		$this->permissionPolicyManager->method('getDefaultPolicy')
			->willReturn($defaultPermissionPolicy);
		$currentPermissionPolicy = new PermissionPolicy();
		$response->method('getPermissionPolicy')
			->willReturn($currentPermissionPolicy);
		$mergedPermissionPolicy = new PermissionPolicy();
		$this->permissionPolicyManager->method('mergePolicies')
			->with($defaultPermissionPolicy, $currentPermissionPolicy)
			->willReturn($mergedPermissionPolicy);
		$mergedPermissionPolicyWithFeaturePolicy = new PermissionPolicy();
		$this->permissionPolicyManager->method('mergeFeaturePolicy')
			->with($mergedPermissionPolicy, $currentPolicy)
			->willReturn($mergedPermissionPolicyWithFeaturePolicy);

		$response->expects($this->once())
			->method('setPermissionPolicy')
			->with($mergedPermissionPolicy);

		$this->middleware->afterController($this->controller, 'test', $response);
	}

	public function testAfterControllerEmpty() {
		$response = $this->createMock(Response::class);
		$emptyPolicy = new EmptyFeaturePolicy();
		$emptyPermissionPolicy = new EmptyPermissionPolicy();
		$response->method('getFeaturePolicy')
			->willReturn($emptyPolicy);
		$response->method('getPermissionPolicy')
			->willReturn($emptyPermissionPolicy);
		$response->expects($this->never())
			->method('setFeaturePolicy');
		$response->expects($this->never())
			->method('setPermissionPolicy');

		$this->middleware->afterController($this->controller, 'test', $response);
	}
}
