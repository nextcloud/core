<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Archive;

use OC\Archive\ZIP;

class ZIPTest extends TestBase {
	protected function getExisting() {
		$dir = \OC::$SERVERROOT . '/tests/data';
		return new ZIP($dir . '/data.zip');
	}

	protected function getNew() {
		return new ZIP(\OC::$server->getTempManager()->getTemporaryFile('.zip'));
	}
}
