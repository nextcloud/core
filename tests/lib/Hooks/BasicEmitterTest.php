<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Hooks;

/**
 * Class DummyEmitter
 *
 * class to make BasicEmitter::emit publicly available
 *
 * @package Test\Hooks
 */
class DummyEmitter extends \OC\Hooks\BasicEmitter {
	public function emitEvent($scope, $method, $arguments = []) {
		$this->emit($scope, $method, $arguments);
	}
}

/**
 * Class EmittedException
 *
 * a dummy exception so we can check if an event is emitted
 *
 * @package Test\Hooks
 */
class EmittedException extends \Exception {
}

class BasicEmitterTest extends \Test\TestCase {
	/**
	 * @var \OC\Hooks\Emitter $emitter
	 */
	protected $emitter;

	protected function setUp(): void {
		parent::setUp();
		$this->emitter = new DummyEmitter();
	}

	public function nonStaticCallBack() {
		throw new EmittedException;
	}

	public static function staticCallBack() {
		throw new EmittedException;
	}

	
	public function testAnonymousFunction() {
		$this->expectException(\Test\Hooks\EmittedException::class);

		$this->emitter->listen('Test', 'test', function () {
			throw new EmittedException;
		});
		$this->emitter->emitEvent('Test', 'test');
	}

	
	public function testStaticCallback() {
		$this->expectException(\Test\Hooks\EmittedException::class);

		$this->emitter->listen('Test', 'test', ['\Test\Hooks\BasicEmitterTest', 'staticCallBack']);
		$this->emitter->emitEvent('Test', 'test');
	}

	
	public function testNonStaticCallback() {
		$this->expectException(\Test\Hooks\EmittedException::class);

		$this->emitter->listen('Test', 'test', [$this, 'nonStaticCallBack']);
		$this->emitter->emitEvent('Test', 'test');
	}

	public function testOnlyCallOnce() {
		$count = 0;
		$listener = function () use (&$count) {
			$count++;
		};
		$this->emitter->listen('Test', 'test', $listener);
		$this->emitter->listen('Test', 'test', $listener);
		$this->emitter->emitEvent('Test', 'test');
		$this->assertEquals(1, $count, 'Listener called an invalid number of times (' . $count . ') expected 1');
	}

	public function testDifferentMethods() {
		$count = 0;
		$listener = function () use (&$count) {
			$count++;
		};
		$this->emitter->listen('Test', 'test', $listener);
		$this->emitter->listen('Test', 'foo', $listener);
		$this->emitter->emitEvent('Test', 'test');
		$this->emitter->emitEvent('Test', 'foo');
		$this->assertEquals(2, $count, 'Listener called an invalid number of times (' . $count . ') expected 2');
	}

	public function testDifferentScopes() {
		$count = 0;
		$listener = function () use (&$count) {
			$count++;
		};
		$this->emitter->listen('Test', 'test', $listener);
		$this->emitter->listen('Bar', 'test', $listener);
		$this->emitter->emitEvent('Test', 'test');
		$this->emitter->emitEvent('Bar', 'test');
		$this->assertEquals(2, $count, 'Listener called an invalid number of times (' . $count . ') expected 2');
	}

	public function testDifferentCallbacks() {
		$count = 0;
		$listener1 = function () use (&$count) {
			$count++;
		};
		$listener2 = function () use (&$count) {
			$count++;
		};
		$this->emitter->listen('Test', 'test', $listener1);
		$this->emitter->listen('Test', 'test', $listener2);
		$this->emitter->emitEvent('Test', 'test');
		$this->assertEquals(2, $count, 'Listener called an invalid number of times (' . $count . ') expected 2');
	}

	
	public function testArguments() {
		$this->expectException(\Test\Hooks\EmittedException::class);

		$this->emitter->listen('Test', 'test', function ($foo, $bar) {
			if ($foo == 'foo' and $bar == 'bar') {
				throw new EmittedException;
			}
		});
		$this->emitter->emitEvent('Test', 'test', ['foo', 'bar']);
	}

	
	public function testNamedArguments() {
		$this->expectException(\Test\Hooks\EmittedException::class);

		$this->emitter->listen('Test', 'test', function ($foo, $bar) {
			if ($foo == 'foo' and $bar == 'bar') {
				throw new EmittedException;
			}
		});
		$this->emitter->emitEvent('Test', 'test', ['foo' => 'foo', 'bar' => 'bar']);
	}

	public function testRemoveAllSpecified() {
		$listener = function () {
			throw new EmittedException;
		};
		$this->emitter->listen('Test', 'test', $listener);
		$this->emitter->removeListener('Test', 'test', $listener);
		$this->emitter->emitEvent('Test', 'test');

		$this->addToAssertionCount(1);
	}

	public function testRemoveWildcardListener() {
		$listener1 = function () {
			throw new EmittedException;
		};
		$listener2 = function () {
			throw new EmittedException;
		};
		$this->emitter->listen('Test', 'test', $listener1);
		$this->emitter->listen('Test', 'test', $listener2);
		$this->emitter->removeListener('Test', 'test');
		$this->emitter->emitEvent('Test', 'test');

		$this->addToAssertionCount(1);
	}

	public function testRemoveWildcardMethod() {
		$listener = function () {
			throw new EmittedException;
		};
		$this->emitter->listen('Test', 'test', $listener);
		$this->emitter->listen('Test', 'foo', $listener);
		$this->emitter->removeListener('Test', null, $listener);
		$this->emitter->emitEvent('Test', 'test');
		$this->emitter->emitEvent('Test', 'foo');

		$this->addToAssertionCount(1);
	}

	public function testRemoveWildcardScope() {
		$listener = function () {
			throw new EmittedException;
		};
		$this->emitter->listen('Test', 'test', $listener);
		$this->emitter->listen('Bar', 'test', $listener);
		$this->emitter->removeListener(null, 'test', $listener);
		$this->emitter->emitEvent('Test', 'test');
		$this->emitter->emitEvent('Bar', 'test');

		$this->addToAssertionCount(1);
	}

	public function testRemoveWildcardScopeAndMethod() {
		$listener = function () {
			throw new EmittedException;
		};
		$this->emitter->listen('Test', 'test', $listener);
		$this->emitter->listen('Test', 'foo', $listener);
		$this->emitter->listen('Bar', 'foo', $listener);
		$this->emitter->removeListener(null, null, $listener);
		$this->emitter->emitEvent('Test', 'test');
		$this->emitter->emitEvent('Test', 'foo');
		$this->emitter->emitEvent('Bar', 'foo');

		$this->addToAssertionCount(1);
	}

	
	public function testRemoveKeepOtherCallback() {
		$this->expectException(\Test\Hooks\EmittedException::class);

		$listener1 = function () {
			throw new EmittedException;
		};
		$listener2 = function () {
			throw new EmittedException;
		};
		$this->emitter->listen('Test', 'test', $listener1);
		$this->emitter->listen('Test', 'test', $listener2);
		$this->emitter->removeListener('Test', 'test', $listener1);
		$this->emitter->emitEvent('Test', 'test');

		$this->addToAssertionCount(1);
	}

	
	public function testRemoveKeepOtherMethod() {
		$this->expectException(\Test\Hooks\EmittedException::class);

		$listener = function () {
			throw new EmittedException;
		};
		$this->emitter->listen('Test', 'test', $listener);
		$this->emitter->listen('Test', 'foo', $listener);
		$this->emitter->removeListener('Test', 'foo', $listener);
		$this->emitter->emitEvent('Test', 'test');

		$this->addToAssertionCount(1);
	}

	
	public function testRemoveKeepOtherScope() {
		$this->expectException(\Test\Hooks\EmittedException::class);

		$listener = function () {
			throw new EmittedException;
		};
		$this->emitter->listen('Test', 'test', $listener);
		$this->emitter->listen('Bar', 'test', $listener);
		$this->emitter->removeListener('Bar', 'test', $listener);
		$this->emitter->emitEvent('Test', 'test');

		$this->addToAssertionCount(1);
	}

	
	public function testRemoveNonExistingName() {
		$this->expectException(\Test\Hooks\EmittedException::class);

		$listener = function () {
			throw new EmittedException;
		};
		$this->emitter->listen('Test', 'test', $listener);
		$this->emitter->removeListener('Bar', 'test', $listener);
		$this->emitter->emitEvent('Test', 'test');

		$this->addToAssertionCount(1);
	}
}
