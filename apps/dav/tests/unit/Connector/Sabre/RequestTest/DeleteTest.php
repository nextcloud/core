<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre\RequestTest;

use OCP\AppFramework\Http;

/**
 * Class DeleteTest
 *
 * @group DB
 *
 * @package OCA\DAV\Tests\unit\Connector\Sabre\RequestTest
 */
class DeleteTest extends RequestTestCase {
	public function testBasicUpload() {
		$user = $this->getUniqueID();
		$view = $this->setupUser($user, 'pass');

		$view->file_put_contents('foo.txt', 'asd');
		$mount = $view->getMount('foo.txt');
		$internalPath = $view->getAbsolutePath();

		// create a ghost file
		$mount->getStorage()->unlink($mount->getInternalPath($internalPath));

		// cache entry still exists
		$this->assertInstanceOf('\OCP\Files\FileInfo', $view->getFileInfo('foo.txt'));

		$response = $this->request($view, $user, 'pass', 'DELETE', '/foo.txt');

		$this->assertEquals(Http::STATUS_NO_CONTENT, $response->getStatus());

		// no longer in the cache
		$this->assertFalse($view->getFileInfo('foo.txt'));
	}
}
