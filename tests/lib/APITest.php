<?php
/**
 * Copyright (c) 2013 Tom Needham <tom@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OCP\IRequest;

class APITest extends \Test\TestCase {

	// Helps build a response variable

	/**
	 * @param string $message
	 */
	public function buildResponse($shipped, $data, $code, $message = null) {
		$resp = new \OC\OCS\Result($data, $code, $message);
		$resp->addHeader('KEY', 'VALUE');
		return [
			'shipped' => $shipped,
			'response' => $resp,
			'app' => $this->getUniqueID('testapp_'),
		];
	}

	// Validate details of the result

	/**
	 * @param \OC\OCS\Result $result
	 */
	public function checkResult($result, $success) {
		// Check response is of correct type
		$this->assertInstanceOf(\OC\OCS\Result::class, $result);
		// Check if it succeeded
		/** @var $result \OC\OCS\Result */
		$this->assertEquals($success, $result->succeeded());
	}

	/**
	 * @return array
	 */
	public function versionDataScriptNameProvider() {
		return [
			// Valid script name
			[
				'/master/ocs/v2.php',
				true,
			],

			// Invalid script names
			[
				'/master/ocs/v2.php/someInvalidPathName',
				false,
			],
			[
				'/master/ocs/v1.php',
				false,
			],
			[
				'',
				false,
			],
		];
	}

	/**
	 * @dataProvider versionDataScriptNameProvider
	 * @param string $scriptName
	 * @param bool $expected
	 */
	public function testIsV2($scriptName, $expected) {
		$request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();
		$request
			->expects($this->once())
			->method('getScriptName')
			->willReturn($scriptName);

		$this->assertEquals($expected, $this->invokePrivate(new \OC_API, 'isV2', [$request]));
	}
}
