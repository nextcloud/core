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
use Test\App\CodeChecker\Mock\TestList;
use Test\TestCase;

class NodeVisitorTest extends TestCase {
	public function providesFilesToCheck() {
		return [
			[[['OCP\AppFramework\IApi', 1006]], 'test-deprecated-use.php'],
			[[['OCP\AppFramework\IApi', 1006]], 'test-deprecated-use-alias.php'],
			[[['AppFramework\IApi', 1001]], 'test-deprecated-use-sub.php'],
			[[['OAF\IApi', 1001]], 'test-deprecated-use-sub-alias.php'],

			[[['OCP\NamespaceName\ClassName::CONSTANT_NAME', 1003]], 'test-deprecated-constant.php'],
			[[['Alias::CONSTANT_NAME', 1003]], 'test-deprecated-constant-alias.php'],
			[[['NamespaceName\ClassName::CONSTANT_NAME', 1003]], 'test-deprecated-constant-sub.php'],
			[[['SubAlias\ClassName::CONSTANT_NAME', 1003]], 'test-deprecated-constant-sub-alias.php'],

			[[
				['OCP\NamespaceName\ClassName::functionName', 1002],
				['OCP\NamespaceName\ClassName::methodName', 1007],
			], 'test-deprecated-function.php'],
			[[
				['Alias::functionName', 1002],
				['Alias::methodName', 1007],
			], 'test-deprecated-function-alias.php'],
			[[
				['NamespaceName\ClassName::functionName', 1002],
				['NamespaceName\ClassName::methodName', 1007],
			], 'test-deprecated-function-sub.php'],
			[[
				['SubAlias\ClassName::functionName', 1002],
				['SubAlias\ClassName::methodName', 1007],
			], 'test-deprecated-function-sub-alias.php'],

			// TODO Failing to resolve variables to classes
			//			[[['OCP\NamespaceName\ClassName::methodName', 1007]], 'test-deprecated-method.php'],
			//			[[['Alias::methodName', 1002]], 'test-deprecated-method-alias.php'],
			//			[[['NamespaceName\ClassName::methodName', 1002]], 'test-deprecated-method-sub.php'],
			//			[[['SubAlias\ClassName::methodName', 1002]], 'test-deprecated-method-sub-alias.php'],
		];
	}

	/**
	 * @dataProvider providesFilesToCheck
	 * @param array $expectedErrors
	 * @param string $fileToVerify
	 */
	public function testMethodsToCheck($expectedErrors, $fileToVerify) {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$checker = new CodeChecker(
			new TestList(new EmptyCheck()),
			false
		);
		$errors = $checker->analyseFile(\OC::$SERVERROOT . "/tests/data/app/code-checker/$fileToVerify");

		$this->assertCount(sizeof($expectedErrors), $errors);

		foreach ($expectedErrors as $int => $expectedError) {
			$this->assertEquals($expectedError[0], $errors[$int]['disallowedToken']);
			$this->assertEquals($expectedError[1], $errors[$int]['errorCode']);
		}
	}
}
