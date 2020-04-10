<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\App\AppStore\Bundles;

use OC\App\AppStore\Bundles\Bundle;
use OCP\IL10N;
use Test\TestCase;

abstract class BundleBase extends TestCase {
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	protected $l10n;
	/** @var Bundle */
	protected $bundle;
	/** @var string */
	protected $bundleIdentifier;
	/** @var string */
	protected $bundleName;
	/** @var array */
	protected $bundleAppIds;

	protected function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
	}

	public function testGetIdentifier() {
		$this->assertSame($this->bundleIdentifier, $this->bundle->getIdentifier());
	}

	public function testGetName() {
		$this->assertSame($this->bundleName, $this->bundle->getName());
	}

	public function testGetAppIdentifiers() {
		$this->assertSame($this->bundleAppIds, $this->bundle->getAppIdentifiers());
	}
}
