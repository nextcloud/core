<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Hooks;

/**
 * Class DummyLegacyEmitter
 *
 * class to make LegacyEmitter::emit publicly available
 *
 * @package Test\Hooks
 */
class DummyLegacyEmitter extends \OC\Hooks\LegacyEmitter {
	public function emitEvent($scope, $method, $arguments = []) {
		$this->emit($scope, $method, $arguments);
	}
}

class LegacyEmitterTest extends BasicEmitterTest {

	//we can't use exceptions here since OC_Hooks catches all exceptions
	private static $emitted = false;

	protected function setUp(): void {
		parent::setUp();

		$this->emitter = new DummyLegacyEmitter();
		self::$emitted = false;
		\OC_Hook::clear('Test','test');
	}

	public static function staticLegacyCallBack() {
		self::$emitted = true;
	}

	public static function staticLegacyArgumentsCallBack($arguments) {
		if ($arguments['foo'] == 'foo' and $arguments['bar'] == 'bar')
			self::$emitted = true;
	}

	public function testLegacyHook() {
		\OC_Hook::connect('Test', 'test', '\Test\Hooks\LegacyEmitterTest', 'staticLegacyCallBack');
		$this->emitter->emitEvent('Test', 'test');
		$this->assertEquals(true, self::$emitted);
	}

	public function testLegacyArguments() {
		\OC_Hook::connect('Test', 'test', '\Test\Hooks\LegacyEmitterTest', 'staticLegacyArgumentsCallBack');
		$this->emitter->emitEvent('Test', 'test', ['foo' => 'foo', 'bar' => 'bar']);
		$this->assertEquals(true, self::$emitted);
	}
}
