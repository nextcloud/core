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

use OC\App\AppStore\Bundles\BundleFetcher;
use OC\App\AppStore\Bundles\CoreBundle;
use OC\App\AppStore\Bundles\EducationBundle;
use OC\App\AppStore\Bundles\EnterpriseBundle;
use OC\App\AppStore\Bundles\GroupwareBundle;
use OC\App\AppStore\Bundles\HubBundle;
use OC\App\AppStore\Bundles\SocialSharingBundle;
use OCP\IL10N;
use Test\TestCase;

class BundleFetcherTest extends TestCase {
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l10n;
	/** @var BundleFetcher */
	private $bundleFetcher;

	protected function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);

		$this->bundleFetcher = new BundleFetcher(
			$this->l10n
		);
	}

	public function testGetBundles() {
		$expected = [
			new EnterpriseBundle($this->l10n),
			new HubBundle($this->l10n),
			new GroupwareBundle($this->l10n),
			new SocialSharingBundle($this->l10n),
			new EducationBundle($this->l10n),
		];
		$this->assertEquals($expected, $this->bundleFetcher->getBundles());
	}

	public function testGetDefaultInstallationBundle() {
		$expected = [
			new CoreBundle($this->l10n),
		];
		$this->assertEquals($expected, $this->bundleFetcher->getDefaultInstallationBundle());
	}

	public function testGetBundleByIdentifier() {
		$this->assertEquals(new EnterpriseBundle($this->l10n), $this->bundleFetcher->getBundleByIdentifier('EnterpriseBundle'));
		$this->assertEquals(new CoreBundle($this->l10n), $this->bundleFetcher->getBundleByIdentifier('CoreBundle'));
		$this->assertEquals(new GroupwareBundle($this->l10n), $this->bundleFetcher->getBundleByIdentifier('GroupwareBundle'));
	}


	public function testGetBundleByIdentifierWithException() {
		$this->expectException(\BadMethodCallException::class);
		$this->expectExceptionMessage('Bundle with specified identifier does not exist');

		$this->bundleFetcher->getBundleByIdentifier('NotExistingBundle');
	}

}
