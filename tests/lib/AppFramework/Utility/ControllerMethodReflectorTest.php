<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2012 Bernhard Posselt <dev@bernhard-posselt.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\AppFramework\Utility;

use OC\AppFramework\Utility\ControllerMethodReflector;

class BaseController {

	/**
	 * @Annotation
	 */
	public function test() {
	}

	/**
	 * @Annotation
	 */
	public function test2() {
	}

	/**
	 * @Annotation
	 */
	public function test3() {
	}
}

class MiddleController extends BaseController {

	/**
	 * @NoAnnotation
	 */
	public function test2() {
	}

	public function test3() {
	}
}

class EndController extends MiddleController {
}

class ControllerMethodReflectorTest extends \Test\TestCase {


	/**
	 * @Annotation
	 */
	public function testReadAnnotation() {
		$reader = new ControllerMethodReflector();
		$reader->reflect(
			'\Test\AppFramework\Utility\ControllerMethodReflectorTest',
			'testReadAnnotation'
		);

		$this->assertTrue($reader->hasAnnotation('Annotation'));
	}

	/**
	 * @Annotation(parameter=value)
	 */
	public function testGetAnnotationParameterSingle() {
		$reader = new ControllerMethodReflector();
		$reader->reflect(
			__CLASS__,
			__FUNCTION__
		);

		$this->assertSame('value', $reader->getAnnotationParameter('Annotation', 'parameter'));
	}

	/**
	 * @Annotation(parameter1=value1, parameter2=value2,parameter3=value3)
	 */
	public function testGetAnnotationParameterMultiple() {
		$reader = new ControllerMethodReflector();
		$reader->reflect(
			__CLASS__,
			__FUNCTION__
		);

		$this->assertSame('value1', $reader->getAnnotationParameter('Annotation', 'parameter1'));
		$this->assertSame('value2', $reader->getAnnotationParameter('Annotation', 'parameter2'));
		$this->assertSame('value3', $reader->getAnnotationParameter('Annotation', 'parameter3'));
	}

	/**
	 * @Annotation
	 * @param test
	 */
	public function testReadAnnotationNoLowercase() {
		$reader = new ControllerMethodReflector();
		$reader->reflect(
			'\Test\AppFramework\Utility\ControllerMethodReflectorTest',
			'testReadAnnotationNoLowercase'
		);

		$this->assertTrue($reader->hasAnnotation('Annotation'));
		$this->assertFalse($reader->hasAnnotation('param'));
	}


	/**
	 * @Annotation
	 * @param int $test
	 */
	public function testReadTypeIntAnnotations() {
		$reader = new ControllerMethodReflector();
		$reader->reflect(
			'\Test\AppFramework\Utility\ControllerMethodReflectorTest',
			'testReadTypeIntAnnotations'
		);

		$this->assertEquals('int', $reader->getType('test'));
	}

	/**
	 * @Annotation
	 * @param int $a
	 * @param int $b
	 */
	public function arguments3($a, float $b, int $c, $d) {
	}

	/**
	 * @requires PHP 7
	 */
	public function testReadTypeIntAnnotationsScalarTypes() {
		$reader = new ControllerMethodReflector();
		$reader->reflect(
			'\Test\AppFramework\Utility\ControllerMethodReflectorTest',
			'arguments3'
		);

		$this->assertEquals('int', $reader->getType('a'));
		$this->assertEquals('float', $reader->getType('b'));
		$this->assertEquals('int', $reader->getType('c'));
		$this->assertNull($reader->getType('d'));
	}


	/**
	 * @Annotation
	 * @param double $test something special
	 */
	public function testReadTypeDoubleAnnotations() {
		$reader = new ControllerMethodReflector();
		$reader->reflect(
			'\Test\AppFramework\Utility\ControllerMethodReflectorTest',
			'testReadTypeDoubleAnnotations'
		);

		$this->assertEquals('double', $reader->getType('test'));
	}

	/**
	 * @Annotation
	 * @param 	string  $foo
	 */
	public function testReadTypeWhitespaceAnnotations() {
		$reader = new ControllerMethodReflector();
		$reader->reflect(
			'\Test\AppFramework\Utility\ControllerMethodReflectorTest',
			'testReadTypeWhitespaceAnnotations'
		);

		$this->assertEquals('string', $reader->getType('foo'));
	}


	public function arguments($arg, $arg2 = 'hi') {
	}
	public function testReflectParameters() {
		$reader = new ControllerMethodReflector();
		$reader->reflect(
			'\Test\AppFramework\Utility\ControllerMethodReflectorTest',
			'arguments'
		);

		$this->assertEquals(['arg' => null, 'arg2' => 'hi'], $reader->getParameters());
	}


	public function arguments2($arg) {
	}
	public function testReflectParameters2() {
		$reader = new ControllerMethodReflector();
		$reader->reflect(
			'\Test\AppFramework\Utility\ControllerMethodReflectorTest',
			'arguments2'
		);

		$this->assertEquals(['arg' => null], $reader->getParameters());
	}


	public function testInheritance() {
		$reader = new ControllerMethodReflector();
		$reader->reflect('Test\AppFramework\Utility\EndController', 'test');

		$this->assertTrue($reader->hasAnnotation('Annotation'));
	}


	public function testInheritanceOverride() {
		$reader = new ControllerMethodReflector();
		$reader->reflect('Test\AppFramework\Utility\EndController', 'test2');

		$this->assertTrue($reader->hasAnnotation('NoAnnotation'));
		$this->assertFalse($reader->hasAnnotation('Annotation'));
	}


	public function testInheritanceOverrideNoDocblock() {
		$reader = new ControllerMethodReflector();
		$reader->reflect('Test\AppFramework\Utility\EndController', 'test3');

		$this->assertFalse($reader->hasAnnotation('Annotation'));
	}
}
