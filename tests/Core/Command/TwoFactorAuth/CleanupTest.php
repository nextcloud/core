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

namespace Core\Command\TwoFactorAuth;

use OC\Core\Command\TwoFactorAuth\Cleanup;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

class CleanupTest extends TestCase {

	/** @var IRegistry|MockObject */
	private $registry;

	/** @var CommandTester */
	private $cmd;

	protected function setUp(): void {
		parent::setUp();

		$this->registry = $this->createMock(IRegistry::class);

		$cmd = new Cleanup($this->registry);
		$this->cmd = new CommandTester($cmd);
	}

	public function testCleanup() {
		$this->registry->expects($this->once())
			->method('cleanUp')
			->with('u2f');

		$rc = $this->cmd->execute([
			'provider-id' => 'u2f',
		]);

		$this->assertEquals(0, $rc);
		$output = $this->cmd->getDisplay();
		$this->assertContains("All user-provider associations for provider u2f have been removed", $output);
	}

}
