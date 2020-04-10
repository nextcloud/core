<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Archive;

use OC\Archive\TAR;

class TARTest extends TestBase {
	protected function getExisting() {
		$dir = \OC::$SERVERROOT . '/tests/data';
		return new TAR($dir . '/data.tar.gz');
	}

	protected function getNew() {
		return new TAR(\OC::$server->getTempManager()->getTemporaryFile('.tar.gz'));
	}
}
