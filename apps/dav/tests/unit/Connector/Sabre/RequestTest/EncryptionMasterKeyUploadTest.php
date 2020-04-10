<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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

use OC\Files\View;
use Test\Traits\EncryptionTrait;

/**
 * Class EncryptionMasterKeyUploadTest
 *
 * @group DB
 *
 * @package OCA\DAV\Tests\Unit\Connector\Sabre\RequestTest
 */
class EncryptionMasterKeyUploadTest extends UploadTest {
	use EncryptionTrait;

	protected function setupUser($name, $password) {
		$this->createUser($name, $password);
		$tmpFolder = \OC::$server->getTempManager()->getTemporaryFolder();
		$this->registerMount($name, '\OC\Files\Storage\Local', '/' . $name, ['datadir' => $tmpFolder]);
		// we use the master key
		\OC::$server->getConfig()->setAppValue('encryption', 'useMasterKey', '1');
		$this->setupForUser($name, $password);
		$this->loginWithEncryption($name);
		return new View('/' . $name . '/files');
	}
}
