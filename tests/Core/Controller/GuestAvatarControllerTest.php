<?php

namespace Core\Controller;

use OC\Core\Controller\GuestAvatarController;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IAvatar;
use OCP\IAvatarManager;
use OCP\ILogger;
use OCP\IRequest;

/**
 * This class provides tests for the guest avatar controller.
 */
class GuestAvatarControllerTest extends \Test\TestCase {

	/**
	 * @var GuestAvatarController
	 */
	private $guestAvatarController;

	/**
	 * @var IRequest|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $request;

	/**
	 * @var IAvatarManager|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $avatarManager;

	/**
	 * @var IAvatar|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $avatar;

	/**
	 * @var \OCP\Files\File|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $file;

	/**
	 * @var ILogger|\PHPUnit_Framework_MockObject_MockObject
	 */
	private $logger;

	/**
	 * Sets up the test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->getMockBuilder(ILogger::class)->getMock();
		$this->request = $this->getMockBuilder(IRequest::class)->getMock();
		$this->avatar = $this->getMockBuilder(IAvatar::class)->getMock();
		$this->avatarManager = $this->getMockBuilder(IAvatarManager::class)->getMock();
		$this->file = $this->getMockBuilder(ISimpleFile::class)->getMock();
		$this->guestAvatarController = new GuestAvatarController(
			'core',
			$this->request,
			$this->avatarManager,
			$this->logger
		);
	}

	/**
	 * Tests getAvatar returns the guest avatar.
	 */
	public function testGetAvatar() {
		$this->avatarManager->expects($this->once())
			->method('getGuestAvatar')
			->with('Peter')
			->willReturn($this->avatar);

		$this->avatar->expects($this->once())
			->method('getFile')
			->with(128)
			->willReturn($this->file);

		$this->file->method('getMimeType')
			->willReturn('image/svg+xml');

		$response = $this->guestAvatarController->getAvatar('Peter', 128);

		$this->assertGreaterThanOrEqual(201, $response->getStatus());
		$this->assertInstanceOf(FileDisplayResponse::class, $response);
	}
}
