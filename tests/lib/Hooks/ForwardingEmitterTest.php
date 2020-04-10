<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Hooks;

use OC\Hooks\PublicEmitter;

class DummyForwardingEmitter extends \OC\Hooks\ForwardingEmitter {
	public function emitEvent($scope, $method, $arguments = []) {
		$this->emit($scope, $method, $arguments);
	}

	/**
	 * @param \OC\Hooks\Emitter $emitter
	 */
	public function forward(\OC\Hooks\Emitter $emitter) {
		parent::forward($emitter);
	}
}

/**
 * Class ForwardingEmitter
 *
 * allows forwarding all listen calls to other emitters
 *
 * @package OC\Hooks
 */
class ForwardingEmitterTest extends BasicEmitterTest {
	public function testSingleForward() {
		$baseEmitter = new PublicEmitter();
		$forwardingEmitter = new DummyForwardingEmitter();
		$forwardingEmitter->forward($baseEmitter);
		$hookCalled = false;
		$forwardingEmitter->listen('Test', 'test', function () use (&$hookCalled) {
			$hookCalled = true;
		});
		$baseEmitter->emit('Test', 'test');
		$this->assertTrue($hookCalled);
	}

	public function testMultipleForwards() {
		$baseEmitter1 = new PublicEmitter();
		$baseEmitter2 = new PublicEmitter();
		$forwardingEmitter = new DummyForwardingEmitter();
		$forwardingEmitter->forward($baseEmitter1);
		$forwardingEmitter->forward($baseEmitter2);
		$hookCalled = 0;
		$forwardingEmitter->listen('Test', 'test1', function () use (&$hookCalled) {
			$hookCalled++;
		});
		$forwardingEmitter->listen('Test', 'test2', function () use (&$hookCalled) {
			$hookCalled++;
		});
		$baseEmitter1->emit('Test', 'test1');
		$baseEmitter1->emit('Test', 'test2');
		$this->assertEquals(2, $hookCalled);
	}

	public function testForwardExistingHooks() {
		$baseEmitter = new PublicEmitter();
		$forwardingEmitter = new DummyForwardingEmitter();
		$hookCalled = false;
		$forwardingEmitter->listen('Test', 'test', function () use (&$hookCalled) {
			$hookCalled = true;
		});
		$forwardingEmitter->forward($baseEmitter);
		$baseEmitter->emit('Test', 'test');
		$this->assertTrue($hookCalled);
	}
}
