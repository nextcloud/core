<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Files_Sharing\Tests;

use OC\Files\View;
use Test\Traits\UserTrait;

/**
 * Class SizePropagationTest
 *
 * @group DB
 *
 * @package OCA\Files_Sharing\Tests
 */
class SizePropagationTest extends TestCase {
	use UserTrait;

	protected function setupUser($name, $password = '') {
		$this->createUser($name, $password);
		$tmpFolder = \OC::$server->getTempManager()->getTemporaryFolder();
		$this->registerMount($name, '\OC\Files\Storage\Local', '/' . $name, ['datadir' => $tmpFolder]);
		$this->loginAsUser($name);
		return new View('/' . $name . '/files');
	}

	public function testSizePropagationWhenOwnerChangesFile() {
		$recipientView = $this->setupUser(self::TEST_FILES_SHARING_API_USER1);

		$ownerView = $this->setupUser(self::TEST_FILES_SHARING_API_USER2);
		$ownerView->mkdir('/sharedfolder/subfolder');
		$ownerView->file_put_contents('/sharedfolder/subfolder/foo.txt', 'bar');

		$this->share(
			\OCP\Share::SHARE_TYPE_USER,
			'/sharedfolder',
			self::TEST_FILES_SHARING_API_USER2,
			self::TEST_FILES_SHARING_API_USER1,
			\OCP\Constants::PERMISSION_ALL
		);
		$ownerRootInfo = $ownerView->getFileInfo('', false);

		$this->loginAsUser(self::TEST_FILES_SHARING_API_USER1);
		$this->assertTrue($recipientView->file_exists('/sharedfolder/subfolder/foo.txt'));
		$recipientRootInfo = $recipientView->getFileInfo('', false);

		// when file changed as owner
		$this->loginAsUser(self::TEST_FILES_SHARING_API_USER2);
		$ownerView->file_put_contents('/sharedfolder/subfolder/foo.txt', 'foobar');

		// size of recipient's root stays the same
		$this->loginAsUser(self::TEST_FILES_SHARING_API_USER1);
		$newRecipientRootInfo = $recipientView->getFileInfo('', false);
		$this->assertEquals($recipientRootInfo->getSize(), $newRecipientRootInfo->getSize());

		// size of owner's root increases
		$this->loginAsUser(self::TEST_FILES_SHARING_API_USER2);
		$newOwnerRootInfo = $ownerView->getFileInfo('', false);
		$this->assertEquals($ownerRootInfo->getSize() + 3, $newOwnerRootInfo->getSize());
	}

	public function testSizePropagationWhenRecipientChangesFile() {
		$recipientView = $this->setupUser(self::TEST_FILES_SHARING_API_USER1);

		$ownerView = $this->setupUser(self::TEST_FILES_SHARING_API_USER2);
		$ownerView->mkdir('/sharedfolder/subfolder');
		$ownerView->file_put_contents('/sharedfolder/subfolder/foo.txt', 'bar');

		$this->share(
			\OCP\Share::SHARE_TYPE_USER,
			'/sharedfolder',
			self::TEST_FILES_SHARING_API_USER2,
			self::TEST_FILES_SHARING_API_USER1,
			\OCP\Constants::PERMISSION_ALL
		);
		$ownerRootInfo = $ownerView->getFileInfo('', false);

		$this->loginAsUser(self::TEST_FILES_SHARING_API_USER1);
		$this->assertTrue($recipientView->file_exists('/sharedfolder/subfolder/foo.txt'));
		$recipientRootInfo = $recipientView->getFileInfo('', false);
		$recipientRootInfoWithMounts = $recipientView->getFileInfo('', true);
		$oldRecipientSize = $recipientRootInfoWithMounts->getSize();

		// when file changed as recipient
		$recipientView->file_put_contents('/sharedfolder/subfolder/foo.txt', 'foobar');

		// size of recipient's root stays the same
		$newRecipientRootInfo = $recipientView->getFileInfo('', false);
		$this->assertEquals($recipientRootInfo->getSize(), $newRecipientRootInfo->getSize());

		// but the size including mountpoints increases
		$newRecipientRootInfo = $recipientView->getFileInfo('', true);
		$this->assertEquals($oldRecipientSize +3, $newRecipientRootInfo->getSize());

		// size of owner's root increases
		$this->loginAsUser(self::TEST_FILES_SHARING_API_USER2);
		$newOwnerRootInfo = $ownerView->getFileInfo('', false);
		$this->assertEquals($ownerRootInfo->getSize() + 3, $newOwnerRootInfo->getSize());
	}
}
