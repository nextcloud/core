<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

namespace Test;

use OC\AppFramework\Bootstrap\Coordinator;
use OCP\IServerContainer;
use function json_encode;
use JsonSerializable;
use OC\InitialStateService;
use OCP\ILogger;
use stdClass;

class InitialStateServiceTest extends TestCase {

	/** @var InitialStateService */
	private $service;

	protected function setUp(): void {
		parent::setUp();

		$this->service = new InitialStateService(
			$this->createMock(ILogger::class),
			$this->createMock(Coordinator::class),
			$this->createMock(IServerContainer::class)
		);
	}

	public function staticData() {
		return [
			['string'],
			[23],
			[2.3],
			[new class implements JsonSerializable {
				public function jsonSerialize() {
					return 3;
				}
			}],
		];
	}

	/**
	 * @dataProvider staticData
	 */
	public function testStaticData($value) {
		$this->service->provideInitialState('test', 'key', $value);
		$data = $this->service->getInitialStates();

		$this->assertEquals(
			['test-key' => json_encode($value)],
			$data
		);
	}

	public function testStaticButInvalidData() {
		$this->service->provideInitialState('test', 'key', new stdClass());
		$data = $this->service->getInitialStates();

		$this->assertEquals(
			[],
			$data
		);
	}

	/**
	 * @dataProvider staticData
	 */
	public function testLazyData($value) {
		$this->service->provideLazyInitialState('test', 'key', function () use ($value) {
			return $value;
		});
		$data = $this->service->getInitialStates();

		$this->assertEquals(
			['test-key' => json_encode($value)],
			$data
		);
	}
}
