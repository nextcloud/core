<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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

use OCA\Files_External\Lib\Storage\FTP;

/**
 * Class FtpTest
 *
 * @group DB
 *
 * @package OCA\Files_External\Tests\Storage
 */
class FtpTest extends \Test\Files\Storage\Storage {
	private $config;

	protected function setUp(): void {
		parent::setUp();

		$id = $this->getUniqueID();
		$this->config = include('files_external/tests/config.ftp.php');
		if (! is_array($this->config) or ! $this->config['run']) {
			$this->markTestSkipped('FTP backend not configured');
		}
		$rootInstance = new FTP($this->config);
		$rootInstance->mkdir($id);

		$this->config['root'] .= '/' . $id; //make sure we have an new empty folder to work in
		$this->instance = new FTP($this->config);
	}

	protected function tearDown(): void {
		if ($this->instance) {
			$this->instance->rmdir('');
		}
		$this->instance = null;

		parent::tearDown();
	}

	/**
	 * ftp has no proper way to handle spaces at the end of file names
	 */
	public function directoryProvider() {
		return array_filter(parent::directoryProvider(), function ($item) {
			return substr($item[0], -1) !== ' ';
		});
	}


	/**
	 * mtime for folders is only with a minute resolution
	 */
	public function testStat() {
		$textFile = \OC::$SERVERROOT . '/tests/data/lorem.txt';
		$ctimeStart = time();
		$this->instance->file_put_contents('/lorem.txt', file_get_contents($textFile));
		$this->assertTrue($this->instance->isReadable('/lorem.txt'));
		$ctimeEnd = time();
		$mTime = $this->instance->filemtime('/lorem.txt');
		$this->assertTrue($this->instance->hasUpdated('/lorem.txt', $ctimeStart - 5));
		$this->assertTrue($this->instance->hasUpdated('/', $ctimeStart - 61));

		// check that ($ctimeStart - 5) <= $mTime <= ($ctimeEnd + 1)
		$this->assertGreaterThanOrEqual(($ctimeStart - 5), $mTime);
		$this->assertLessThanOrEqual(($ctimeEnd + 1), $mTime);
		$this->assertEquals(filesize($textFile), $this->instance->filesize('/lorem.txt'));

		$stat = $this->instance->stat('/lorem.txt');
		//only size and mtime are required in the result
		$this->assertEquals($stat['size'], $this->instance->filesize('/lorem.txt'));
		$this->assertEquals($stat['mtime'], $mTime);

		if ($this->instance->touch('/lorem.txt', 100) !== false) {
			$mTime = $this->instance->filemtime('/lorem.txt');
			$this->assertEquals($mTime, 100);
		}

		$mtimeStart = time();

		$this->instance->unlink('/lorem.txt');
		$this->assertTrue($this->instance->hasUpdated('/', $mtimeStart - 61));
	}
}
