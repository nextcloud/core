<?php
/**
 * @copyright 2016, Roeland Jago Douma <roeland@famdouma.nl>
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
namespace Test\Repair\Owncloud;

use OC\Repair\Owncloud\CleanPreviewsBackgroundJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\ILogger;
use OCP\IUserManager;
use Test\TestCase;

class CleanPreviewsBackgroundJobTest extends TestCase {
	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	private $rootFolder;

	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	private $logger;

	/** @var IJobList|\PHPUnit_Framework_MockObject_MockObject */
	private $jobList;

	/** @var ITimeFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $timeFactory;

	/** @var CleanPreviewsBackgroundJob */
	private $job;

	/** @var  IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;

	public function setUp() {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->userManager->expects($this->any())->method('userExists')->willReturn(true);

		$this->job = new CleanPreviewsBackgroundJob(
			$this->rootFolder,
			$this->logger,
			$this->jobList,
			$this->timeFactory,
			$this->userManager
		);
	}

	public function testCleanupPreviewsUnfinished() {
		$userFolder = $this->createMock(Folder::class);
		$userRoot = $this->createMock(Folder::class);
		$thumbnailFolder = $this->createMock(Folder::class);

		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo('myuid'))
			->willReturn($userFolder);

		$userFolder->method('getParent')->willReturn($userRoot);

		$userRoot->method('get')
			->with($this->equalTo('thumbnails'))
			->willReturn($thumbnailFolder);

		$previewFolder1 = $this->createMock(Folder::class);

		$previewFolder1->expects($this->once())
			->method('delete');

		$thumbnailFolder->method('getDirectoryListing')
			->willReturn([$previewFolder1]);
		$thumbnailFolder->expects($this->never())
			->method('delete');

		$this->timeFactory->method('getTime')
			->will($this->onConsecutiveCalls(100, 200));

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				$this->equalTo(CleanPreviewsBackgroundJob::class),
				$this->equalTo(['uid' => 'myuid'])
			);

		$this->logger->expects($this->at(0))
			->method('info')
			->with($this->equalTo('Started preview cleanup for myuid'));
		$this->logger->expects($this->at(1))
			->method('info')
			->with($this->equalTo('New preview cleanup scheduled for myuid'));

		$this->job->run(['uid' => 'myuid']);
	}

	public function testCleanupPreviewsFinished() {
		$userFolder = $this->createMock(Folder::class);
		$userRoot = $this->createMock(Folder::class);
		$thumbnailFolder = $this->createMock(Folder::class);

		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo('myuid'))
			->willReturn($userFolder);

		$userFolder->method('getParent')->willReturn($userRoot);

		$userRoot->method('get')
			->with($this->equalTo('thumbnails'))
			->willReturn($thumbnailFolder);

		$previewFolder1 = $this->createMock(Folder::class);

		$previewFolder1->expects($this->once())
			->method('delete');

		$thumbnailFolder->method('getDirectoryListing')
			->willReturn([$previewFolder1]);

		$this->timeFactory->method('getTime')
			->will($this->onConsecutiveCalls(100, 101));

		$this->jobList->expects($this->never())
			->method('add');

		$this->logger->expects($this->at(0))
			->method('info')
			->with($this->equalTo('Started preview cleanup for myuid'));
		$this->logger->expects($this->at(1))
			->method('info')
			->with($this->equalTo('Preview cleanup done for myuid'));

		$thumbnailFolder->expects($this->once())
			->method('delete');

		$this->job->run(['uid' => 'myuid']);
	}


	public function testNoUserFolder() {
		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo('myuid'))
			->willThrowException(new NotFoundException());

		$this->logger->expects($this->at(0))
			->method('info')
			->with($this->equalTo('Started preview cleanup for myuid'));
		$this->logger->expects($this->at(1))
			->method('info')
			->with($this->equalTo('Preview cleanup done for myuid'));

		$this->job->run(['uid' => 'myuid']);
	}

	public function testNoThumbnailFolder() {
		$userFolder = $this->createMock(Folder::class);
		$userRoot = $this->createMock(Folder::class);

		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo('myuid'))
			->willReturn($userFolder);

		$userFolder->method('getParent')->willReturn($userRoot);

		$userRoot->method('get')
			->with($this->equalTo('thumbnails'))
			->willThrowException(new NotFoundException());

		$this->logger->expects($this->at(0))
			->method('info')
			->with($this->equalTo('Started preview cleanup for myuid'));
		$this->logger->expects($this->at(1))
			->method('info')
			->with($this->equalTo('Preview cleanup done for myuid'));

		$this->job->run(['uid' => 'myuid']);
	}

	public function testNotPermittedToDelete() {
		$userFolder = $this->createMock(Folder::class);
		$userRoot = $this->createMock(Folder::class);
		$thumbnailFolder = $this->createMock(Folder::class);

		$this->rootFolder->method('getUserFolder')
			->with($this->equalTo('myuid'))
			->willReturn($userFolder);

		$userFolder->method('getParent')->willReturn($userRoot);

		$userRoot->method('get')
			->with($this->equalTo('thumbnails'))
			->willReturn($thumbnailFolder);

		$previewFolder1 = $this->createMock(Folder::class);

		$previewFolder1->expects($this->once())
			->method('delete')
			->willThrowException(new NotPermittedException());

		$thumbnailFolder->method('getDirectoryListing')
			->willReturn([$previewFolder1]);

		$this->timeFactory->method('getTime')
			->will($this->onConsecutiveCalls(100, 101));

		$this->jobList->expects($this->never())
			->method('add');

		$this->logger->expects($this->at(0))
			->method('info')
			->with($this->equalTo('Started preview cleanup for myuid'));
		$this->logger->expects($this->at(1))
			->method('info')
			->with($this->equalTo('Preview cleanup done for myuid'));

		$thumbnailFolder->expects($this->once())
			->method('delete')
			->willThrowException(new NotPermittedException());

		$this->job->run(['uid' => 'myuid']);
	}
}
