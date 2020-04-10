<?php
/**
 * Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files;

use OC\Files\FileInfo;
use OC\Files\Storage\Home;
use OC\Files\Storage\Temporary;
use OCP\IConfig;
use OCP\IUser;
use Test\TestCase;
use Test\Traits\UserTrait;

class FileInfoTest extends TestCase {
	use UserTrait;

	private $config;

	protected function setUp(): void {
		parent::setUp();
		$this->createUser('foo', 'foo');
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
	}

	public function testIsMountedHomeStorage() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('foo');
		$user->method('getHome')
			->willReturn('foo');

		$fileInfo = new FileInfo(
			'',
			new Home(['user' => $user]),
			'', [], null);
		$this->assertFalse($fileInfo->isMounted());
	}

	public function testIsMountedNonHomeStorage() {
		$fileInfo = new FileInfo(
			'',
			new Temporary(),
			'', [], null);
		$this->assertTrue($fileInfo->isMounted());
	}
}
