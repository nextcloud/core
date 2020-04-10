<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\FederatedFileSharing\Tests;

use OCA\FederatedFileSharing\Controller\RequestHandlerController;
use OCP\AppFramework\Http\DataResponse;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProvider;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Share;
use OCP\Share\IShare;

/**
 * Class RequestHandlerTest
 *
 * @package OCA\FederatedFileSharing\Tests
 * @group DB
 */
class RequestHandlerControllerTest extends \Test\TestCase {

	private $owner = 'owner';
	private $user1 = 'user1';
	private $user2 = 'user2';
	private $ownerCloudId = 'owner@server0.org';
	private $user1CloudId = 'user1@server1.org';
	private $user2CloudId = 'user2@server2.org';

	/** @var RequestHandlerController */
	private $requestHandler;

	/** @var  \OCA\FederatedFileSharing\FederatedShareProvider|\PHPUnit_Framework_MockObject_MockObject */
	private $federatedShareProvider;

	/** @var  \OCA\FederatedFileSharing\Notifications|\PHPUnit_Framework_MockObject_MockObject */
	private $notifications;

	/** @var  \OCA\FederatedFileSharing\AddressHandler|\PHPUnit_Framework_MockObject_MockObject */
	private $addressHandler;

	/** @var  IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;

	/** @var  IShare|\PHPUnit_Framework_MockObject_MockObject */
	private $share;

	/** @var  ICloudIdManager|\PHPUnit_Framework_MockObject_MockObject */
	private $cloudIdManager;

	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	private $logger;

	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var IDBConnection|\PHPUnit_Framework_MockObject_MockObject */
	private $connection;

	/** @var Share\IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;

	/** @var ICloudFederationFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $cloudFederationFactory;

	/** @var ICloudFederationProviderManager|\PHPUnit_Framework_MockObject_MockObject */
	private $cloudFederationProviderManager;

	/** @var ICloudFederationProvider|\PHPUnit_Framework_MockObject_MockObject */
	private $cloudFederationProvider;

	/** @var ICloudFederationShare|\PHPUnit_Framework_MockObject_MockObject */
	private $cloudFederationShare;

	protected function setUp(): void {

		$this->share = $this->getMockBuilder(IShare::class)->getMock();
		$this->federatedShareProvider = $this->getMockBuilder('OCA\FederatedFileSharing\FederatedShareProvider')
			->disableOriginalConstructor()->getMock();
		$this->federatedShareProvider->expects($this->any())
			->method('isOutgoingServer2serverShareEnabled')->willReturn(true);
		$this->federatedShareProvider->expects($this->any())
			->method('isIncomingServer2serverShareEnabled')->willReturn(true);
		$this->federatedShareProvider->expects($this->any())->method('getShareById')
			->willReturn($this->share);

		$this->notifications = $this->getMockBuilder('OCA\FederatedFileSharing\Notifications')
			->disableOriginalConstructor()->getMock();
		$this->addressHandler = $this->getMockBuilder('OCA\FederatedFileSharing\AddressHandler')
			->disableOriginalConstructor()->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)->getMock();
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->request = $this->createMock(IRequest::class);
		$this->connection = $this->createMock(IDBConnection::class);
		$this->shareManager = $this->createMock(Share\IManager::class);
		$this->cloudFederationFactory = $this->createMock(ICloudFederationFactory::class);
		$this->cloudFederationProviderManager = $this->createMock(ICloudFederationProviderManager::class);
		$this->cloudFederationProvider = $this->createMock(ICloudFederationProvider::class);
		$this->cloudFederationShare = $this->createMock(ICloudFederationShare::class);


		$this->logger = $this->createMock(ILogger::class);

		$this->requestHandler = new RequestHandlerController(
			'federatedfilesharing',
			$this->request,
			$this->federatedShareProvider,
			$this->connection,
			$this->shareManager,
			$this->notifications,
			$this->addressHandler,
			$this->userManager,
			$this->cloudIdManager,
			$this->logger,
			$this->cloudFederationFactory,
			$this->cloudFederationProviderManager
		);

	}

	function testCreateShare() {
		// simulate a post request
		$_POST['remote'] = 'localhost';
		$_POST['token'] = 'token';
		$_POST['name'] = 'name';
		$_POST['owner'] = $this->owner;
		$_POST['sharedBy'] = $this->user1;
		$_POST['shareWith'] = $this->user2;
		$_POST['remoteId'] = 1;
		$_POST['sharedByFederatedId'] = $this->user1CloudId;
		$_POST['ownerFederatedId'] = $this->ownerCloudId;

		$this->cloudFederationFactory->expects($this->once())->method('getCloudFederationShare')
			->with(
					$this->user2,
					'name',
					'',
					1,
					$this->ownerCloudId,
					$this->owner,
					$this->user1CloudId,
					$this->user1,
					'token',
					'user',
					'file'
			)->willReturn($this->cloudFederationShare);

		/** @var ICloudFederationProvider|\PHPUnit_Framework_MockObject_MockObject $provider */
		$this->cloudFederationProviderManager->expects($this->once())
			->method('getCloudFederationProvider')
			->with('file')
			->willReturn($this->cloudFederationProvider);

		$this->cloudFederationProvider->expects($this->once())->method('shareReceived')
			->with($this->cloudFederationShare);

		$result = $this->requestHandler->createShare();

		$this->assertInstanceOf(DataResponse::class, $result);

	}

	function testDeclineShare() {

		$id = 42;
		$_POST['token'] = 'token';

		$notification = [
			'sharedSecret' => 'token',
			'message' => 'Recipient declined the share'
		];

		$this->cloudFederationProviderManager->expects($this->once())
			->method('getCloudFederationProvider')
			->with('file')
			->willReturn($this->cloudFederationProvider);

		$this->cloudFederationProvider->expects($this->once())
			->method('notificationReceived')
			->with('SHARE_DECLINED', $id, $notification);

		$result = $this->requestHandler->declineShare($id);

		$this->assertInstanceOf(DataResponse::class, $result);

	}


	function testAcceptShare() {

		$id = 42;
		$_POST['token'] = 'token';

		$notification = [
			'sharedSecret' => 'token',
			'message' => 'Recipient accept the share'
		];

		$this->cloudFederationProviderManager->expects($this->once())
			->method('getCloudFederationProvider')
			->with('file')
			->willReturn($this->cloudFederationProvider);

		$this->cloudFederationProvider->expects($this->once())
			->method('notificationReceived')
			->with('SHARE_ACCEPTED', $id, $notification);

		$result = $this->requestHandler->acceptShare($id);

		$this->assertInstanceOf(DataResponse::class, $result);

	}


}
