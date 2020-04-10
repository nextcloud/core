<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\Files_External\Tests\Controller;

use OCA\Files_External\Controller\GlobalStoragesController;
use OCA\Files_External\Service\BackendService;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;

class GlobalStoragesControllerTest extends StoragesControllerTest {
	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->getMockBuilder('\OCA\Files_External\Service\GlobalStoragesService')
			->disableOriginalConstructor()
			->getMock();

		$this->service->method('getVisibilityType')
			->willReturn(BackendService::VISIBILITY_ADMIN);

		$this->controller = new GlobalStoragesController(
			'files_external',
			$this->createMock(IRequest::class),
			$this->createMock(IL10N::class),
			$this->service,
			$this->createMock(ILogger::class)
		);
	}
}
