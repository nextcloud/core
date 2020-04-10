<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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
 * Class HelperTest
 *
 * @group DB
 */
class HelperTest extends TestCase {

	/**
	 * test set and get share folder
	 */
	public function testSetGetShareFolder() {
		$this->assertSame('/', \OCA\Files_Sharing\Helper::getShareFolder());

		\OCA\Files_Sharing\Helper::setShareFolder('/Shared/Folder');

		$sharedFolder = \OCA\Files_Sharing\Helper::getShareFolder();
		$this->assertSame('/Shared/Folder', \OCA\Files_Sharing\Helper::getShareFolder());
		$this->assertTrue(\OC\Files\Filesystem::is_dir($sharedFolder));

		// cleanup
		\OC::$server->getConfig()->deleteSystemValue('share_folder');

	}

}
