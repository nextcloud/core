<?php
/**
 * Copyright (c) 2015 Thomas Müller <deepdiver@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file. */

namespace Test\Files;

use OC\Files\Storage\Local;
use OC\Files\View;
use OCP\Files\InvalidPathException;

/**
 * Class PathVerificationTest
 *
 * @group DB
 *
 * @package Test\Files
 */
class PathVerificationTest extends \Test\TestCase {

	/**
	 * @var \OC\Files\View
	 */
	private $view;

	protected function setUp(): void {
		parent::setUp();
		$this->view = new View();
	}

	
	public function testPathVerificationFileNameTooLong() {
		$this->expectException(\OCP\Files\InvalidPathException::class);
		$this->expectExceptionMessage('File name is too long');

		$fileName = str_repeat('a', 500);
		$this->view->verifyPath('', $fileName);
	}


	/**
	 * @dataProvider providesEmptyFiles
	 */
	public function testPathVerificationEmptyFileName($fileName) {
		$this->expectException(\OCP\Files\InvalidPathException::class);
		$this->expectExceptionMessage('Empty filename is not allowed');

		$this->view->verifyPath('', $fileName);
	}

	public function providesEmptyFiles() {
		return [
			[''],
			[' '],
		];
	}

	/**
	 * @dataProvider providesDotFiles
	 */
	public function testPathVerificationDotFiles($fileName) {
		$this->expectException(\OCP\Files\InvalidPathException::class);
		$this->expectExceptionMessage('Dot files are not allowed');

		$this->view->verifyPath('', $fileName);
	}

	public function providesDotFiles() {
		return [
			['.'],
			['..'],
			[' .'],
			[' ..'],
			['. '],
			['.. '],
			[' . '],
			[' .. '],
		];
	}

	/**
	 * @dataProvider providesAstralPlane
	 */
	public function testPathVerificationAstralPlane($fileName) {
		$connection = \OC::$server->getDatabaseConnection();

		if (!$connection->supports4ByteText()) {
			$this->expectException(InvalidPathException::class);
			$this->expectExceptionMessage('File name contains at least one invalid character');
		} else {
			$this->addToAssertionCount(1);
		}

		$this->view->verifyPath('', $fileName);
	}

	public function providesAstralPlane() {
		return [
			// this is the monkey emoji - http://en.wikipedia.org/w/index.php?title=%F0%9F%90%B5&redirect=no
			['🐵'],
			['🐵.txt'],
			['txt.💩'],
			['💩🐵.txt'],
			['💩🐵'],
		];
	}

	/**
	 * @dataProvider providesInvalidCharsPosix
	 */
	public function testPathVerificationInvalidCharsPosix($fileName) {
		$this->expectException(\OCP\Files\InvalidCharacterInPathException::class);

		$storage = new Local(['datadir' => '']);

		$fileName = " 123{$fileName}456 ";
		self::invokePrivate($storage, 'verifyPosixPath', [$fileName]);
	}

	public function providesInvalidCharsPosix() {
		return [
			[\chr(0)],
			[\chr(1)],
			[\chr(2)],
			[\chr(3)],
			[\chr(4)],
			[\chr(5)],
			[\chr(6)],
			[\chr(7)],
			[\chr(8)],
			[\chr(9)],
			[\chr(10)],
			[\chr(11)],
			[\chr(12)],
			[\chr(13)],
			[\chr(14)],
			[\chr(15)],
			[\chr(16)],
			[\chr(17)],
			[\chr(18)],
			[\chr(19)],
			[\chr(20)],
			[\chr(21)],
			[\chr(22)],
			[\chr(23)],
			[\chr(24)],
			[\chr(25)],
			[\chr(26)],
			[\chr(27)],
			[\chr(28)],
			[\chr(29)],
			[\chr(30)],
			[\chr(31)],
			['/'],
			['\\'],
		];
	}

	/**
	 * @dataProvider providesValidPosixPaths
	 */
	public function testPathVerificationValidPaths($fileName) {
		$storage = new Local(['datadir' => '']);

		self::invokePrivate($storage, 'verifyPosixPath', [$fileName]);
		// nothing thrown
		$this->addToAssertionCount(1);
	}

	public function providesValidPosixPaths() {
		return [
			['simple'],
			['simple.txt'],
			['\''],
			['`'],
			['%'],
			['()'],
			['[]'],
			['!'],
			['$'],
			['_'],
		];
	}
}
