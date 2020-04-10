<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre;

use OC\Files\FileInfo;
use OC\Files\View;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Storage;
use OCP\Share\IManager;
use OCP\Share\IShare;

/**
 * Class NodeTest
 *
 * @group DB
 * @package OCA\DAV\Tests\unit\Connector\Sabre
 */
class NodeTest extends \Test\TestCase {
	public function davPermissionsProvider() {
		return [
			[\OCP\Constants::PERMISSION_ALL, 'file', false, false, 'RGDNVW'],
			[\OCP\Constants::PERMISSION_ALL, 'dir', false, false, 'RGDNVCK'],
			[\OCP\Constants::PERMISSION_ALL, 'file', true, false, 'SRGDNVW'],
			[\OCP\Constants::PERMISSION_ALL, 'file', true, true, 'SRMGDNVW'],
			[\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_SHARE, 'file', true, false, 'SGDNVW'],
			[\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_UPDATE, 'file', false, false, 'RGD'],
			[\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_DELETE, 'file', false, false, 'RGNVW'],
			[\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_CREATE, 'file', false, false, 'RGDNVW'],
			[\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_READ, 'file', false, false, 'RDNVW'],
			[\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_CREATE, 'dir', false, false, 'RGDNV'],
			[\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_READ, 'dir', false, false, 'RDNVCK'],
		];
	}

	/**
	 * @dataProvider davPermissionsProvider
	 */
	public function testDavPermissions($permissions, $type, $shared, $mounted, $expected) {
		$info = $this->getMockBuilder(FileInfo::class)
			->disableOriginalConstructor()
			->setMethods(['getPermissions', 'isShared', 'isMounted', 'getType'])
			->getMock();
		$info->expects($this->any())
			->method('getPermissions')
			->willReturn($permissions);
		$info->expects($this->any())
			->method('isShared')
			->willReturn($shared);
		$info->expects($this->any())
			->method('isMounted')
			->willReturn($mounted);
		$info->expects($this->any())
			->method('getType')
			->willReturn($type);
		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()
			->getMock();

		$node = new  \OCA\DAV\Connector\Sabre\File($view, $info);
		$this->assertEquals($expected, $node->getDavPermissions());
	}

	public function sharePermissionsProvider() {
		return [
			[\OCP\Files\FileInfo::TYPE_FILE, null, 1, 1],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 3, 3],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 5, 1],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 7, 3],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 9, 1],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 11, 3],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 13, 1],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 15, 3],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 17, 17],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 19, 19],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 21, 17],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 23, 19],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 25, 17],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 27, 19],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 29, 17],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 30, 18],
			[\OCP\Files\FileInfo::TYPE_FILE, null, 31, 19],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 1, 1],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 3, 3],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 5, 5],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 7, 7],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 9, 9],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 11, 11],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 13, 13],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 15, 15],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 17, 17],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 19, 19],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 21, 21],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 23, 23],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 25, 25],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 27, 27],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 29, 29],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 30, 30],
			[\OCP\Files\FileInfo::TYPE_FOLDER, null, 31, 31],
			[\OCP\Files\FileInfo::TYPE_FOLDER, 'shareToken', 7, 7],
		];
	}

	/**
	 * @dataProvider sharePermissionsProvider
	 */
	public function testSharePermissions($type, $user, $permissions, $expected) {
		$storage = $this->getMockBuilder(Storage::class)
			->disableOriginalConstructor()
			->getMock();
		$storage->method('getPermissions')->willReturn($permissions);

		$mountpoint = $this->getMockBuilder(IMountPoint::class)
			->disableOriginalConstructor()
			->getMock();
		$mountpoint->method('getMountPoint')->willReturn('myPath');
		$shareManager = $this->getMockBuilder(IManager::class)->disableOriginalConstructor()->getMock();
		$share = $this->getMockBuilder(IShare::class)->disableOriginalConstructor()->getMock();

		if ($user === null) {
			$shareManager->expects($this->never())->method('getShareByToken');
			$share->expects($this->never())->method('getPermissions');
		} else {
			$shareManager->expects($this->once())->method('getShareByToken')->with($user)
				->willReturn($share);
			$share->expects($this->once())->method('getPermissions')->willReturn($permissions);
		}

		$info = $this->getMockBuilder(FileInfo::class)
			->disableOriginalConstructor()
			->setMethods(['getStorage', 'getType', 'getMountPoint', 'getPermissions'])
			->getMock();

		$info->method('getStorage')->willReturn($storage);
		$info->method('getType')->willReturn($type);
		$info->method('getMountPoint')->willReturn($mountpoint);
		$info->method('getPermissions')->willReturn($permissions);

		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()
			->getMock();

		$node = new  \OCA\DAV\Connector\Sabre\File($view, $info);
		$this->invokePrivate($node, 'shareManager', [$shareManager]);
		$this->assertEquals($expected, $node->getSharePermissions($user));
	}
}
