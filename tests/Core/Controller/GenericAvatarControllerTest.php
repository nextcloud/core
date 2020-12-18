<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
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

namespace Tests\Core\Controller;

use OC\Core\Controller\GenericAvatarController;
use OCP\IAvatarManager;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class GenericAvatarControllerTest extends \Test\TestCase {

	/** @var GenericAvatarController */
	private $genericAvatarController;

	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IAvatarManager|\PHPUnit\Framework\MockObject\MockObject */
	private $avatarManager;
	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l;
	/** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->getMockBuilder(IRequest::class)->getMock();
		$this->avatarManager = $this->getMockBuilder('OCP\IAvatarManager')->getMock();
		$this->l = $this->getMockBuilder(IL10N::class)->getMock();
		$this->l->method('t')->willReturnArgument(0);
		$this->logger = $this->getMockBuilder(LoggerInterface::class)->getMock();

		$this->genericAvatarController = new GenericAvatarController(
			'core',
			$this->request,
			$this->avatarManager,
			$this->l,
			$this->logger
		);
	}

	public function dataSanitizeSize(): array {
		return [
			[-1, 64],
			[32, 64],

			[64, 64],
			[65, 64],

			[95, 64],
			[96, 128],

			[127, 128],
			[128, 128],
			[129, 128],

			[191, 128],
			[192, 256],

			[255, 256],
			[256, 256],
			[257, 256],

			[383, 256],
			[384, 512],

			[511, 512],
			[512, 512],

			[8192, 512],

		];
	}

	/**
	 * @dataProvider dataSanitizeSize
	 *
	 * @param int $inputSize
	 * @param int $expectedSize
	 */
	public function testSanitizeSize(int $inputSize, int $expectedSize) {
		$this->assertEquals($expectedSize, $this->invokePrivate($this->genericAvatarController, 'sanitizeSize', [$inputSize]));
	}
}
