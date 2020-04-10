<?php
/**
 * Copyright (c) 2014 Lukas Reschke <lukas@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Security;

use OC\Security\SecureRandom;

class SecureRandomTest extends \Test\TestCase {

	public function stringGenerationProvider() {
		return [
			[0, 0],
			[1, 1],
			[128, 128],
			[256, 256],
			[1024, 1024],
			[2048, 2048],
			[64000, 64000],
		];
	}

	public static function charCombinations() {
		return [
			['CHAR_LOWER', '[a-z]'],
			['CHAR_UPPER', '[A-Z]'],
			['CHAR_DIGITS', '[0-9]'],
		];
	}

	/** @var SecureRandom */
	protected $rng;

	protected function setUp(): void {
		parent::setUp();
		$this->rng = new \OC\Security\SecureRandom();
	}

	/**
	 * @dataProvider stringGenerationProvider
	 */
	function testGetLowStrengthGeneratorLength($length, $expectedLength) {
		$generator = $this->rng;

		$this->assertEquals($expectedLength, strlen($generator->generate($length)));
	}

	/**
	 * @dataProvider stringGenerationProvider
	 */
	function testMediumLowStrengthGeneratorLength($length, $expectedLength) {
		$generator = $this->rng;

		$this->assertEquals($expectedLength, strlen($generator->generate($length)));
	}

	/**
	 * @dataProvider stringGenerationProvider
	 */
	function testUninitializedGenerate($length, $expectedLength) {
		$this->assertEquals($expectedLength, strlen($this->rng->generate($length)));
	}

	/**
	 * @dataProvider charCombinations
	 */
	public function testScheme($charName, $chars) {
		$generator = $this->rng;
		$scheme = constant('OCP\Security\ISecureRandom::' . $charName);
		$randomString = $generator->generate(100, $scheme);
		$matchesRegex = preg_match('/^'.$chars.'+$/', $randomString);
		$this->assertSame(1, $matchesRegex);
	}
}
