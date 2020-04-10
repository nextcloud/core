<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\DAV\Tests\Unit\DAV\Controller;

use OCA\DAV\Controller\DirectController;
use OCA\DAV\Db\Direct;
use OCA\DAV\Db\DirectMapper;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;
use Test\TestCase;

class DirectControllerTest extends TestCase {

	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	private $rootFolder;

	/** @var DirectMapper|\PHPUnit_Framework_MockObject_MockObject */
	private $directMapper;

	/** @var ISecureRandom|\PHPUnit_Framework_MockObject_MockObject */
	private $random;

	/** @var ITimeFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $timeFactory;

	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;

	/** @var DirectController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->directMapper = $this->createMock(DirectMapper::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->controller = new DirectController(
			'dav',
			$this->createMock(IRequest::class),
			$this->rootFolder,
			'awesomeUser',
			$this->directMapper,
			$this->random,
			$this->timeFactory,
			$this->urlGenerator
		);
	}

	public function testGetUrlNonExistingFileId() {
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with('awesomeUser')
			->willReturn($userFolder);

		$userFolder->method('getById')
			->with(101)
			->willReturn([]);

		$this->expectException(OCSNotFoundException::class);
		$this->controller->getUrl(101);
	}

	public function testGetUrlForFolder() {
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with('awesomeUser')
			->willReturn($userFolder);

		$folder = $this->createMock(Folder::class);

		$userFolder->method('getById')
			->with(101)
			->willReturn([$folder]);

		$this->expectException(OCSBadRequestException::class);
		$this->controller->getUrl(101);
	}

	public function testGetUrlValid() {
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')
			->with('awesomeUser')
			->willReturn($userFolder);

		$file = $this->createMock(File::class);

		$this->timeFactory->method('getTime')
			->willReturn(42);

		$userFolder->method('getById')
			->with(101)
			->willReturn([$file]);

		$this->random->method('generate')
			->with(
				60,
				ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS
			)->willReturn('superduperlongtoken');

		$this->directMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Direct $direct) {
				$this->assertSame('awesomeUser', $direct->getUserId());
				$this->assertSame(101, $direct->getFileId());
				$this->assertSame('superduperlongtoken', $direct->getToken());
				$this->assertSame(42 + 60*60*8, $direct->getExpiration());
			});

		$this->urlGenerator->method('getAbsoluteURL')
			->willReturnCallback(function (string $url) {
				return 'https://my.nextcloud/'.$url;
			});

		$result = $this->controller->getUrl(101);

		$this->assertInstanceOf(DataResponse::class, $result);
		$this->assertSame([
			'url' => 'https://my.nextcloud/remote.php/direct/superduperlongtoken',
		], $result->getData());
	}
}
