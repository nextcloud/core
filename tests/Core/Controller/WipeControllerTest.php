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

namespace Tests\Core\Controller;

use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Token\RemoteWipe;
use OC\Core\Controller\WipeController;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class WipeControllerTest extends TestCase {

	/** @var RemoteWipe|MockObject */
	private $remoteWipe;

	/** @var WipeController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->remoteWipe = $this->createMock(RemoteWipe::class);
		$this->controller = new WipeController(
			'core',
			$this->createMock(IRequest::class),
			$this->remoteWipe);
	}

	public function dataTest() {
		return [
			// valid token, could perform operation, valid result
			[ true,  true,  true],
			[ true, false, false],
			[false,  true, false],
			[false, false, false],
		];
	}

	/**
	 * @param bool $valid
	 * @param bool $couldPerform
	 * @param bool $result
	 *
	 * @dataProvider dataTest
	 */
	public function testCheckWipe(bool $valid, bool $couldPerform, bool $result) {
		if (!$valid) {
			$this->remoteWipe->method('start')
				->with('mytoken')
				->willThrowException(new InvalidTokenException());
		} else {
			$this->remoteWipe->method('start')
				->with('mytoken')
				->willReturn($couldPerform);
		}

		$result = $this->controller->checkWipe('mytoken');

		if (!$valid || !$couldPerform) {
			$this->assertSame(Http::STATUS_NOT_FOUND, $result->getStatus());
			$this->assertSame([], $result->getData());
		} else {
			$this->assertSame(Http::STATUS_OK, $result->getStatus());
			$this->assertSame(['wipe' => true], $result->getData());
		}
	}

	/**
	 * @param bool $valid
	 * @param bool $couldPerform
	 * @param bool $result
	 *
	 * @dataProvider dataTest
	 */
	public function testWipeDone(bool $valid, bool $couldPerform, bool $result) {
		if (!$valid) {
			$this->remoteWipe->method('finish')
				->with('mytoken')
				->willThrowException(new InvalidTokenException());
		} else {
			$this->remoteWipe->method('finish')
				->with('mytoken')
				->willReturn($couldPerform);
		}

		$result = $this->controller->wipeDone('mytoken');

		if (!$valid || !$couldPerform) {
			$this->assertSame(Http::STATUS_NOT_FOUND, $result->getStatus());
			$this->assertSame([], $result->getData());
		} else {
			$this->assertSame(Http::STATUS_OK, $result->getStatus());
			$this->assertSame([], $result->getData());
		}
	}
}
