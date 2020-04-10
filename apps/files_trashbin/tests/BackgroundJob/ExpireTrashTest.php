<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OCA\Files_Trashbin\Tests\BackgroundJob;

use OCA\Files_Trashbin\BackgroundJob\ExpireTrash;
use OCP\BackgroundJob\IJobList;
use OCP\IUserManager;

class ExpireTrashTest extends \Test\TestCase {
	public function testConstructAndRun() {
		$backgroundJob = new ExpireTrash(
			$this->createMock(IUserManager::class),
			$this->getMockBuilder('OCA\Files_Trashbin\Expiration')->disableOriginalConstructor()->getMock()
		);

		$jobList = $this->createMock(IJobList::class);

		/** @var \OC\BackgroundJob\JobList $jobList */
		$backgroundJob->execute($jobList);
		$this->addToAssertionCount(1);
	}
}
