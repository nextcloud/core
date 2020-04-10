<?php
/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Settings\Tests\Controller;

use OC\Authentication\TwoFactorAuth\EnforcementState;
use OC\Authentication\TwoFactorAuth\MandatoryTwoFactor;
use OCA\Settings\Controller\TwoFactorSettingsController;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TwoFactorSettingsControllerTest extends TestCase {

	/** @var IRequest|MockObject */
	private $request;

	/** @var MandatoryTwoFactor|MockObject */
	private $mandatoryTwoFactor;

	/** @var TwoFactorSettingsController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->mandatoryTwoFactor = $this->createMock(MandatoryTwoFactor::class);

		$this->controller = new TwoFactorSettingsController(
			'settings',
			$this->request,
			$this->mandatoryTwoFactor
		);
	}

	public function testIndex() {
		$state = new EnforcementState(true);
		$this->mandatoryTwoFactor->expects($this->once())
			->method('getState')
			->willReturn($state);
		$expected = new JSONResponse($state);

		$resp = $this->controller->index();

		$this->assertEquals($expected, $resp);
	}

	public function testUpdate() {
		$state = new EnforcementState(true);
		$this->mandatoryTwoFactor->expects($this->once())
			->method('setState')
			->with($this->equalTo(new EnforcementState(true)));
		$this->mandatoryTwoFactor->expects($this->once())
			->method('getState')
			->willReturn($state);
		$expected = new JSONResponse($state);

		$resp = $this->controller->update(true);

		$this->assertEquals($expected, $resp);
	}

}
