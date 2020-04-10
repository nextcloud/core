<?php

/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Session;

class MemoryTest extends Session {

	protected function setUp(): void {
		parent::setUp();
		$this->instance = new \OC\Session\Memory($this->getUniqueID());
	}

	
	public function testThrowsExceptionOnGetId() {
		$this->expectException(\OCP\Session\Exceptions\SessionNotAvailableException::class);

		$this->instance->getId();
	}

}
