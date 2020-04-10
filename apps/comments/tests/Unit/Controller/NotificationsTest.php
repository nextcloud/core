<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Comments\Tests\Unit\Controller;

use OCA\Comments\Controller\Notifications;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Test\TestCase;

class NotificationsTest extends TestCase {
	/** @var Notifications */
	protected $notificationsController;

	/** @var ICommentsManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $commentsManager;

	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	protected $rootFolder;

	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	protected $session;

	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $notificationManager;

	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $urlGenerator;

	protected function setUp(): void {
		parent::setUp();

		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->notificationsController = new Notifications(
			'comments',
			$this->createMock(IRequest::class),
			$this->commentsManager,
			$this->rootFolder,
			$this->urlGenerator,
			$this->notificationManager,
			$this->session
		);
	}

	public function testViewGuestRedirect() {
		$this->commentsManager->expects($this->never())
			->method('get');

		$this->rootFolder->expects($this->never())
			->method('getUserFolder');

		$this->session->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$this->notificationManager->expects($this->never())
			->method('createNotification');
		$this->notificationManager->expects($this->never())
			->method('markProcessed');

		$this->urlGenerator->expects($this->exactly(2))
			->method('linkToRoute')
			->withConsecutive(
				['comments.Notifications.view', ['id' => '42']],
				['core.login.showLoginForm', ['redirect_url' => 'link-to-comment']]
			)
			->willReturnMap([
				['comments.Notifications.view', ['id' => '42'], 'link-to-comment'],
				['core.login.showLoginForm', ['redirect_url' => 'link-to-comment'], 'link-to-login'],
			]);

		/** @var RedirectResponse $response */
		$response = $this->notificationsController->view('42');
		$this->assertInstanceOf(RedirectResponse::class, $response);
		$this->assertSame('link-to-login', $response->getRedirectURL());
	}

	public function testViewSuccess() {
		$comment = $this->createMock(IComment::class);
		$comment->expects($this->any())
			->method('getObjectType')
			->willReturn('files');
		$comment->expects($this->any())
			->method('getId')
			->willReturn('1234');

		$this->commentsManager->expects($this->any())
			->method('get')
			->with('42')
			->willReturn($comment);

		$file = $this->createMock(Node::class);
		$folder = $this->createMock(Folder::class);
		$user = $this->createMock(IUser::class);

		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->willReturn($folder);

		$folder->expects($this->once())
			->method('getById')
			->willReturn([$file]);

		$this->session->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$user->expects($this->any())
			->method('getUID')
			->willReturn('user');

		$notification = $this->createMock(INotification::class);
		$notification->expects($this->any())
			->method($this->anything())
			->willReturn($notification);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('markProcessed')
			->with($notification);

		$response = $this->notificationsController->view('42');
		$this->assertInstanceOf(RedirectResponse::class, $response);
	}

	public function testViewInvalidComment() {
		$this->commentsManager->expects($this->any())
			->method('get')
			->with('42')
			->will($this->throwException(new NotFoundException()));

		$this->rootFolder->expects($this->never())
			->method('getUserFolder');

		$user = $this->createMock(IUser::class);

		$this->session->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$user->expects($this->any())
			->method('getUID')
			->willReturn('user');

		$this->notificationManager->expects($this->never())
			->method('createNotification');
		$this->notificationManager->expects($this->never())
			->method('markProcessed');

		$response = $this->notificationsController->view('42');
		$this->assertInstanceOf(NotFoundResponse::class, $response);
	}

	public function testViewNoFile() {
		$comment = $this->createMock(IComment::class);
		$comment->expects($this->any())
			->method('getObjectType')
			->willReturn('files');
		$comment->expects($this->any())
			->method('getId')
			->willReturn('1234');

		$this->commentsManager->expects($this->any())
			->method('get')
			->with('42')
			->willReturn($comment);

		$folder = $this->createMock(Folder::class);

		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->willReturn($folder);

		$folder->expects($this->once())
			->method('getById')
			->willReturn([]);

		$user = $this->createMock(IUser::class);

		$this->session->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$user->expects($this->any())
			->method('getUID')
			->willReturn('user');

		$notification = $this->createMock(INotification::class);
		$notification->expects($this->any())
			->method($this->anything())
			->willReturn($notification);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('markProcessed')
			->with($notification);

		$response = $this->notificationsController->view('42');
		$this->assertInstanceOf(NotFoundResponse::class, $response);
	}
}
