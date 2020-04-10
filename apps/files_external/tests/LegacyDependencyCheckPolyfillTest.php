<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_External\Tests;

use OCA\Files_External\Lib\MissingDependency;

class LegacyDependencyCheckPolyfillTest extends \Test\TestCase {

	/**
	 * @return MissingDependency[]
	 */
	public static function checkDependencies() {
		return [
			(new MissingDependency('dependency'))->setMessage('missing dependency'),
			(new MissingDependency('program'))->setMessage('cannot find program'),
		];
	}

	public function testCheckDependencies() {
		$trait = $this->getMockForTrait('\OCA\Files_External\Lib\LegacyDependencyCheckPolyfill');
		$trait->expects($this->once())
			->method('getStorageClass')
			->willReturn('\OCA\Files_External\Tests\LegacyDependencyCheckPolyfillTest');

		$dependencies = $trait->checkDependencies();
		$this->assertCount(2, $dependencies);
		$this->assertEquals('dependency', $dependencies[0]->getDependency());
		$this->assertEquals('missing dependency', $dependencies[0]->getMessage());
		$this->assertEquals('program', $dependencies[1]->getDependency());
		$this->assertEquals('cannot find program', $dependencies[1]->getMessage());
	}

}
