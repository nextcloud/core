<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace Test\Repair\Owncloud;

use OC\App\AppStore\Bundles\Bundle;
use OC\App\AppStore\Bundles\BundleFetcher;
use OC\Installer;
use OC\Repair\Owncloud\InstallCoreBundle;
use OCP\IConfig;
use OCP\Migration\IOutput;
use Test\TestCase;

class InstallCoreBundleTest extends TestCase {
	/** @var BundleFetcher|\PHPUnit_Framework_MockObject_MockObject */
	private $bundleFetcher;
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var Installer|\PHPUnit_Framework_MockObject_MockObject */
	private $installer;
	/** @var InstallCoreBundle */
	private $installCoreBundle;

	public function setUp() {
		parent::setUp();
		$this->bundleFetcher = $this->createMock(BundleFetcher::class);
		$this->config = $this->createMock(IConfig::class);
		$this->installer = $this->createMock(Installer::class);

		$this->installCoreBundle = new InstallCoreBundle(
			$this->bundleFetcher,
			$this->config,
			$this->installer
		);
	}

	public function testGetName() {
		$this->assertSame('Install new core bundle components', $this->installCoreBundle->getName());
	}

	public function testRunOlder() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('version', '0.0.0')
			->willReturn('12.0.0.15');
		$this->bundleFetcher
			->expects($this->never())
			->method('getDefaultInstallationBundle');
		/** @var IOutput|\PHPUnit_Framework_MockObject_MockObject $output */
		$output = $this->createMock(IOutput::class);
		$output
			->expects($this->never())
			->method('info');
		$output
			->expects($this->never())
			->method('warning');

		$this->installCoreBundle->run($output);
	}

	public function testRunWithException() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('version', '0.0.0')
			->willReturn('12.0.0.14');
		$bundle = $this->createMock(Bundle::class);
		$this->bundleFetcher
			->expects($this->once())
			->method('getDefaultInstallationBundle')
			->willReturn([
				$bundle,
			]);
		$this->installer
			->expects($this->once())
			->method('installAppBundle')
			->with($bundle)
			->willThrowException(new \Exception('ExceptionText'));
		/** @var IOutput|\PHPUnit_Framework_MockObject_MockObject $output */
		$output = $this->createMock(IOutput::class);
		$output
			->expects($this->never())
			->method('info');
		$output
			->expects($this->once())
			->method('warning')
			->with('Could not install core app bundle: ExceptionText');

		$this->installCoreBundle->run($output);
	}

	public function testRun() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('version', '0.0.0')
			->willReturn('12.0.0.14');
		$bundle = $this->createMock(Bundle::class);
		$this->bundleFetcher
			->expects($this->once())
			->method('getDefaultInstallationBundle')
			->willReturn([
				$bundle,
			]);
		$this->installer
			->expects($this->once())
			->method('installAppBundle')
			->with($bundle);
		/** @var IOutput|\PHPUnit_Framework_MockObject_MockObject $output */
		$output = $this->createMock(IOutput::class);
		$output
			->expects($this->once())
			->method('info')
			->with('Successfully installed core app bundle.');
		$output
			->expects($this->never())
			->method('warning');

		$this->installCoreBundle->run($output);
	}
}
