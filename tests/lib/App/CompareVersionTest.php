<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace Test\App;

use InvalidArgumentException;
use OC\App\CompareVersion;
use Test\TestCase;

class CompareVersionTest extends TestCase {

	/** @var CompareVersion */
	private $compare;

	protected function setUp(): void {
		parent::setUp();

		$this->compare = new CompareVersion();
	}

	public function comparisonData() {
		return [
			// Compatible versions
			['13.0.0.3', '13.0.0', '>=', true],
			['13.0.0.3', '13.0.0', '<=', true],
			['13.0.0', '13.0.0', '>=', true],
			['13.0.0', '13.0', '<=', true],
			['13.0.0', '13', '>=', true],
			['13.0.1', '13', '>=', true],
			['13.0.1', '13', '<=', true],
			['13.0.1.9', '13', '<=', true],
			['13.0.1-beta.1', '13', '<=', true],
			['7.4.14', '7.4', '<=', true],
			['7.4.14-ubuntu', '7.4', '<=', true],
			['7.4.14-ubuntu', '7.4.15', '<=', true],
			['7.4.16-ubuntu', '7.4.15', '<=', false],
			// Incompatible major versions
			['13.0.0.3', '13.0.0', '<', false],
			['12.0.0', '13.0.0', '>=', false],
			['12.0.0', '13.0', '>=', false],
			['12.0.0', '13', '>=', false],
			['7.4.15-ubuntu', '7.4.15', '>=', true],
			// Incompatible minor and patch versions
			['13.0.0', '13.0.1', '>=', false],
			['13.0.0', '13.1', '>=', false],
			// Compatible minor and patch versions
			['13.0.1', '13.0.0', '>=', true],
			['13.2.0', '13.1', '>=', true],
		];
	}

	/**
	 * @dataProvider comparisonData
	 */
	public function testComparison(string $actualVersion, string $requiredVersion,
		string $comparator, bool $expected) {
		$isCompatible = $this->compare->isCompatible($actualVersion, $requiredVersion,
			$comparator);

		$this->assertEquals($expected, $isCompatible);
	}

	public function testInvalidServerVersion() {
		$actualVersion = '13';
		$this->expectException(InvalidArgumentException::class);

		$this->compare->isCompatible($actualVersion, '13.0.0');
	}

	public function testInvalidRequiredVersion() {
		$actualVersion = '13.0.0';
		$this->expectException(InvalidArgumentException::class);

		$this->compare->isCompatible($actualVersion, '13.0.0.9');
	}
}
