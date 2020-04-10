<?php

/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace Test\Support\CrashReport;

use Exception;
use OC\Support\CrashReport\Registry;
use OCP\Support\CrashReport\ICollectBreadcrumbs;
use OCP\Support\CrashReport\IMessageReporter;
use OCP\Support\CrashReport\IReporter;
use Test\TestCase;

class RegistryTest extends TestCase {

	/** @var Registry */
	private $registry;

	protected function setUp(): void {
		parent::setUp();

		$this->registry = new Registry();
	}

	/**
	 * Doesn't assert anything, just checks whether anything "explodes"
	 */
	public function testDelegateToNone() {
		$exception = new Exception('test');

		$this->registry->delegateReport($exception);
		$this->addToAssertionCount(1);
	}

	public function testDelegateBreadcrumbCollection() {
		$reporter1 = $this->createMock(IReporter::class);
		$reporter2 = $this->createMock(ICollectBreadcrumbs::class);
		$message = 'hello';
		$category = 'log';
		$reporter2->expects($this->once())
			->method('collect')
			->with($message, $category);

		$this->registry->register($reporter1);
		$this->registry->register($reporter2);
		$this->registry->delegateBreadcrumb($message, $category);
	}

	public function testDelegateToAll() {
		$reporter1 = $this->createMock(IReporter::class);
		$reporter2 = $this->createMock(IReporter::class);
		$exception = new Exception('test');
		$reporter1->expects($this->once())
			->method('report')
			->with($exception);
		$reporter2->expects($this->once())
			->method('report')
			->with($exception);

		$this->registry->register($reporter1);
		$this->registry->register($reporter2);
		$this->registry->delegateReport($exception);
	}

	public function testDelegateMessage() {
		$reporter1 = $this->createMock(IReporter::class);
		$reporter2 = $this->createMock(IMessageReporter::class);
		$message = 'hello';
		$reporter2->expects($this->once())
			->method('reportMessage')
			->with($message, []);

		$this->registry->register($reporter1);
		$this->registry->register($reporter2);
		$this->registry->delegateMessage($message);
	}

}
