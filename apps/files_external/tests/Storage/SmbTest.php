<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Juan Pablo Villafáñez <jvillafanez@solidgear.es>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\Files_External\Tests\Storage;

use OC\Files\Notify\Change;
use OC\Files\Notify\RenameChange;
use OCA\Files_External\Lib\Storage\SMB;
use OCP\Files\Notify\IChange;

/**
 * Class SmbTest
 *
 * @group DB
 *
 * @package OCA\Files_External\Tests\Storage
 */
class SmbTest extends \Test\Files\Storage\Storage {
	/**
	 * @var SMB instance
	 */
	protected $instance;

	protected function setUp(): void {
		parent::setUp();

		$id = $this->getUniqueID();
		$config = include('files_external/tests/config.smb.php');
		if (!is_array($config) or !$config['run']) {
			$this->markTestSkipped('Samba backend not configured');
		}
		if (substr($config['root'], -1, 1) != '/') {
			$config['root'] .= '/';
		}
		$config['root'] .= $id; //make sure we have an new empty folder to work in
		$this->instance = new SMB($config);
		$this->instance->mkdir('/');
	}

	protected function tearDown(): void {
		if ($this->instance) {
			$this->instance->rmdir('');
		}

		parent::tearDown();
	}

	public function directoryProvider() {
		// doesn't support leading/trailing spaces
		return [['folder']];
	}

	public function testRenameWithSpaces() {
		$this->instance->mkdir('with spaces');
		$result = $this->instance->rename('with spaces', 'foo bar');
		$this->assertTrue($result);
		$this->assertTrue($this->instance->is_dir('foo bar'));
	}

	public function testStorageId() {
		$this->instance = new SMB([
			'host' => 'testhost',
			'user' => 'testuser',
			'password' => 'somepass',
			'share' => 'someshare',
			'root' => 'someroot',
		]);
		$this->assertEquals('smb::testuser@testhost//someshare//someroot/', $this->instance->getId());
		$this->instance = null;
	}

	public function testNotifyGetChanges() {
		$notifyHandler = $this->instance->notify('');
		sleep(1); //give time for the notify to start
		$this->instance->file_put_contents('/newfile.txt', 'test content');
		sleep(1);
		$this->instance->rename('/newfile.txt', 'renamed.txt');
		sleep(1);
		$this->instance->unlink('/renamed.txt');
		sleep(1); //time for all changes to be processed

		$changes = [];
		$count = 0;
		// wait up to 10 seconds for incoming changes
		while (count($changes) < 3 && $count < 10) {
			$changes = array_merge($changes, $notifyHandler->getChanges());
			$count++;
			sleep(1);
		}
		$notifyHandler->stop();

		$expected = [
			new Change(IChange::ADDED, 'newfile.txt'),
			new RenameChange(IChange::RENAMED, 'newfile.txt', 'renamed.txt'),
			new Change(IChange::REMOVED, 'renamed.txt')
		];

		foreach ($expected as $expectedChange) {
			$this->assertContains($expectedChange, $changes, 'Actual changes are:' . PHP_EOL . print_r($expected, true), false, false); // dont check object identity
		}
	}

	public function testNotifyListen() {
		$notifyHandler = $this->instance->notify('');
		usleep(100 * 1000); //give time for the notify to start
		$this->instance->file_put_contents('/newfile.txt', 'test content');
		$this->instance->unlink('/newfile.txt');
		usleep(100 * 1000); //time for all changes to be processed

		$result = null;

		// since the notify handler buffers untill we start listening we will get the above changes
		$notifyHandler->listen(function (IChange $change) use (&$result) {
			$result = $change;
			return false;//stop listening
		});

		$this->assertEquals(new Change(IChange::ADDED, 'newfile.txt'), $result);
	}

	public function testRenameRoot() {
		// root can't be renamed
		$this->assertFalse($this->instance->rename('', 'foo1'));

		$this->instance->mkdir('foo2');
		$this->assertFalse($this->instance->rename('foo2', ''));
		$this->instance->rmdir('foo2');
	}

	public function testUnlinkRoot() {
		// root can't be deleted
		$this->assertFalse($this->instance->unlink(''));
	}

	public function testRmdirRoot() {
		// root can't be deleted
		$this->assertFalse($this->instance->rmdir(''));
	}
}
