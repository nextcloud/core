<?php
/**
 * Copyright (c) 2014 Thomas Müller <deepdiver@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

use OC\Session\Memory;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;

/**
 * Starts a new session before each test execution
 */
class StartSessionListener implements TestListener {

	use TestListenerDefaultImplementation;

	public function endTest(Test $test, float $time): void {
		// reopen the session - only allowed for memory session
		if (\OC::$server->getSession() instanceof Memory) {
			/** @var $session Memory */
			$session = \OC::$server->getSession();
			$session->reopen();
		}
	}

}
