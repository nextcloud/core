<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
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

namespace OCA\Files_Sharing\Tests;

/**
 * Class UnshareChildrenTest
 *
 * @group DB
 *
 * @package OCA\Files_Sharing\Tests
 */
class UnshareChildrenTest extends TestCase {

	protected $subsubfolder;

	const TEST_FOLDER_NAME = '/folder_share_api_test';

	private static $tempStorage;

	protected function setUp(): void {
		parent::setUp();

		\OCP\Util::connectHook('OC_Filesystem', 'post_delete', '\OCA\Files_Sharing\Hooks', 'unshareChildren');

		$this->folder = self::TEST_FOLDER_NAME;
		$this->subfolder = '/subfolder_share_api_test';
		$this->subsubfolder = '/subsubfolder_share_api_test';

		$this->filename = '/share-api-test';

		// save file with content
		$this->view->mkdir($this->folder);
		$this->view->mkdir($this->folder . $this->subfolder);
		$this->view->mkdir($this->folder . $this->subfolder . $this->subsubfolder);
		$this->view->file_put_contents($this->folder . $this->filename, $this->data);
		$this->view->file_put_contents($this->folder . $this->subfolder . $this->filename, $this->data);
	}

	protected function tearDown(): void {
		if ($this->view) {
			$this->view->deleteAll($this->folder);
		}

		self::$tempStorage = null;

		parent::tearDown();
	}

	/**
	 * @medium
	 */
	public function testUnshareChildren() {

		$fileInfo2 = \OC\Files\Filesystem::getFileInfo($this->folder);

		$this->share(
			\OCP\Share::SHARE_TYPE_USER,
			$this->folder,
			self::TEST_FILES_SHARING_API_USER1,
			self::TEST_FILES_SHARING_API_USER2,
			\OCP\Constants::PERMISSION_ALL
		);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		// one folder should be shared with the user
		$shares = $this->shareManager->getSharedWith(self::TEST_FILES_SHARING_API_USER2, \OCP\Share::SHARE_TYPE_USER);
		$this->assertCount(1, $shares);

		// move shared folder to 'localDir'
		\OC\Files\Filesystem::mkdir('localDir');
		$result = \OC\Files\Filesystem::rename($this->folder, '/localDir/' . $this->folder);
		$this->assertTrue($result);

		\OC\Files\Filesystem::unlink('localDir');

		self::loginHelper(self::TEST_FILES_SHARING_API_USER2);

		// after the parent directory was deleted the share should be unshared
		$shares = $this->shareManager->getSharedWith(self::TEST_FILES_SHARING_API_USER2, \OCP\Share::SHARE_TYPE_USER);
		$this->assertEmpty($shares);

		self::loginHelper(self::TEST_FILES_SHARING_API_USER1);

		// the folder for the owner should still exists
		$this->assertTrue(\OC\Files\Filesystem::file_exists($this->folder));
	}
}
