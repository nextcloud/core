<?php
/**
 * @copyright Copyright (c) 2020, Jakub Gawron <kubatek94@gmail.com>
 *
 * @author Jakub Gawron <kubatek94@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Files\Storage\Local {

	class ImplHolder {
		/**
		 * @var callable|null
		 */
		public static $impl = null;
	}

	function rename(...$args) {
		if (!is_callable(ImplHolder::$impl)) {
			throw new \RuntimeException('Mock rename implementation has to be set on ImplHolder::$impl');
		}
		return call_user_func_array(ImplHolder::$impl, $args);
	}
}

namespace Test\Files\Storage\Local {

	use OC\Files\Storage\Local\DirectoryRenamer;
	use OC\Files\Storage\Local\ImplHolder;

	class DirectoryRenamerTest extends \Test\TestCase {

		/**
		 * @dataProvider renameImplFailsSoFallbackIsCalledProvider
		 * @param callable $impl
		 */
		public function testFallbackIsCalledWhenRenameFails(callable $impl) {
			ImplHolder::$impl = $impl;

			$fallbackCalled = false;

			$renamer = new DirectoryRenamer(function() use (&$fallbackCalled) {
				$fallbackCalled = true;
				return true;
			});

			$this->assertTrue(
				$renamer->rename('//mnt/nfs/foo/y', '//mnt/nfs/bar/x')
			);

			$this->assertTrue($fallbackCalled, 'Fallback handler wasn\'t called');
		}

		/**
		 * @dataProvider renameImplPassesSoFallbackIsNotCalledProvider
		 * @param callable $impl
		 */
		public function testFallbackIsNotCalledWhenRenamePasses(callable $impl) {
			ImplHolder::$impl = $impl;

			$fallbackCalled = false;

			$renamer = new DirectoryRenamer(function() use (&$fallbackCalled) {
				$fallbackCalled = true;
				return false;
			});

			$this->assertTrue(
				$renamer->rename('//mnt/nfs/foo/y', '//mnt/nfs/bar/x')
			);

			$this->assertFalse($fallbackCalled, 'Fallback handler was called');
		}

		/**
		 * @dataProvider renameImplFailsUnexpectedlySoFallbackIsNotCalledProvider
		 * @param callable $impl
		 * @param null|string $expectedWarning
		 */
		public function testFallbackIsNotCalledWhenRenameFailsUnexpectedly(callable $impl, ?string $expectedWarning) {
			ImplHolder::$impl = $impl;

			$fallbackCalled = false;

			$renamer = new DirectoryRenamer(function() use (&$fallbackCalled) {
				$fallbackCalled = true;
				return false;
			});

			$warnings = [];

			if ($expectedWarning) {
				set_error_handler(function($_, $errmsg) use (&$warnings) {
					$warnings[] = $errmsg;
				}, E_USER_WARNING);
			}

			$this->assertFalse(
				$renamer->rename('//mnt/nfs/foo/y', '//mnt/nfs/bar/x')
			);

			$this->assertFalse($fallbackCalled, 'Fallback handler was called');

			if ($expectedWarning) {
				$this->assertContains($expectedWarning, $warnings);
			}
		}

		public function renameImplFailsSoFallbackIsCalledProvider() {
			return [
				[
					function ($oldname, $newname) {
						trigger_error('rename(): The first argument to copy() function cannot be a directory', E_USER_WARNING);
						trigger_error('rename('.$oldname.','.$newname.'): Invalid cross-device link', E_USER_WARNING);
						return false;
					},
				],
				[
					function ($oldname, $newname) {
						trigger_error('rename('.$oldname.','.$newname.'): Invalid cross-device link', E_USER_WARNING);
						return false;
					},
				],
				[
					function ($oldname, $newname) {
						trigger_error('rename('.$oldname.','.$newname.'): Cross-device link', E_USER_WARNING);
						return false;
					},
				],
			];
		}

		public function renameImplPassesSoFallbackIsNotCalledProvider() {
			return [
				[
					function () {
						return true;
					},
				],
			];
		}

		public function renameImplFailsUnexpectedlySoFallbackIsNotCalledProvider() {
			return [
				[
					function () {
						trigger_error('rename(): The first argument to copy() function cannot be a directory', E_USER_WARNING);
						trigger_error('unable to rename, destination directory is not writable', E_USER_WARNING);
						return false;
					},
					'unable to rename, destination directory is not writable',
				],
				[
					function () {
						trigger_error('unable to rename, destination directory is not writable', E_USER_WARNING);
						return false;
					},
					'unable to rename, destination directory is not writable',
				],
				[
					function ($oldname, $newname) {
						trigger_error('unable to rename, destination directory is not writable', E_USER_WARNING);
						trigger_error('rename('.$oldname.','.$newname.'): Invalid cross-device link', E_USER_WARNING);
						return false;
					},
					'unable to rename, destination directory is not writable',
				],
			];
		}
	}
}
