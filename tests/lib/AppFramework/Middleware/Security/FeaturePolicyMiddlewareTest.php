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

use OC\AppFramework\Middleware\Security\FeaturePolicyMiddleware;
use OC\Security\FeaturePolicy\FeaturePolicy;
use OC\Security\FeaturePolicy\FeaturePolicyManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\EmptyFeaturePolicy;
use OCP\AppFramework\Http\Response;
use PHPUnit\Framework\MockObject\MockObject;

class FeaturePolicyMiddlewareTest extends \Test\TestCase {

	/** @var FeaturePolicyMiddleware|MockObject */
	private $middleware;
	/** @var Controller|MockObject */
	private $controller;
	/** @var FeaturePolicyManager|MockObject */
	private $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->controller = $this->createMock(Controller::class);
		$this->manager = $this->createMock(FeaturePolicyManager::class);
		$this->middleware = new FeaturePolicyMiddleware(
			$this->manager
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
		$this->manager->method('getDefaultPolicy')
			->willReturn($defaultPolicy);
		$this->manager->method('mergePolicies')
			->with($defaultPolicy, $currentPolicy)
			->willReturn($mergedPolicy);
		$response->expects($this->once())
			->method('setFeaturePolicy')
			->with($mergedPolicy);

		$this->middleware->afterController($this->controller, 'test', $response);
	}

	public function testAfterControllerEmptyCSP() {
		$response = $this->createMock(Response::class);
		$emptyPolicy = new EmptyFeaturePolicy();
		$response->method('getFeaturePolicy')
			->willReturn($emptyPolicy);
		$response->expects($this->never())
			->method('setFeaturePolicy');

		$this->middleware->afterController($this->controller, 'test', $response);
	}
}
