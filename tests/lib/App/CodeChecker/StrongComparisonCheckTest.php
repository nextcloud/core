<?php
/**
 * Copyright (c) 2015 Joas Schilling <nickvergessen@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\App\CodeChecker;

use OC\App\CodeChecker\CodeChecker;
use OC\App\CodeChecker\EmptyCheck;
use OC\App\CodeChecker\StrongComparisonCheck;
use Test\TestCase;

class StrongComparisonCheckTest extends TestCase {

	/**
	 * @dataProvider providesFilesToCheck
	 * @param string $expectedErrorToken
	 * @param int $expectedErrorCode
	 * @param string $fileToVerify
	 */
	public function testFindInvalidUsage($expectedErrorToken, $expectedErrorCode, $fileToVerify) {
		$checker = new CodeChecker(
			new StrongComparisonCheck(new EmptyCheck()),
			false
		);
		$errors = $checker->analyseFile(\OC::$SERVERROOT . "/tests/data/app/code-checker/$fileToVerify");

		$this->assertEquals(1, count($errors));
		$this->assertEquals($expectedErrorCode, $errors[0]['errorCode']);
		$this->assertEquals($expectedErrorToken, $errors[0]['disallowedToken']);
	}

	public function providesFilesToCheck() {
		return [
			['==', 1005, 'test-equal.php'],
			['!=', 1005, 'test-not-equal.php'],
		];
	}

	/**
	 * @dataProvider validFilesData
	 * @param string $fileToVerify
	 */
	public function testPassValidUsage($fileToVerify) {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$checker = new CodeChecker(
			new StrongComparisonCheck(new EmptyCheck()),
			false
		);
		$errors = $checker->analyseFile(\OC::$SERVERROOT . "/tests/data/app/code-checker/$fileToVerify");

		$this->assertEquals(0, count($errors));
	}

	public function validFilesData() {
		return [
			['test-deprecated-use.php'],
			['test-deprecated-use-alias.php'],
			['test-deprecated-use-sub.php'],
			['test-deprecated-use-sub-alias.php'],
			['test-extends.php'],
			['test-implements.php'],
			['test-static-call.php'],
			['test-const.php'],
			['test-new.php'],
			['test-use.php'],
			['test-identical-operator.php'],
		];
	}
}
