<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019, Daniel Kesselberg (mail@danielkesselberg.de)
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

namespace Tests\Core\Command\Config;

use OC\Core\Command\App\Disable;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

/**
 * Class AppsDisableTest
 *
 * @group DB
 */
class AppsDisableTest extends TestCase {

	/** @var CommandTester */
	private $commandTester;

	protected function setUp(): void {
		parent::setUp();

		$command = new Disable(
			\OC::$server->getAppManager()
		);

		$this->commandTester = new CommandTester($command);

		\OC::$server->getAppManager()->enableApp('admin_audit');
		\OC::$server->getAppManager()->enableApp('comments');
	}

	/**
	 * @dataProvider dataCommandInput
	 * @param $appId
	 * @param $groups
	 * @param $statusCode
	 * @param $output
	 */
	public function testCommandInput($appId, $statusCode, $output): void {
		$input = ['app-id' => $appId];

		$this->commandTester->execute($input);

		$this->assertStringContainsString($output, $this->commandTester->getDisplay());
		$this->assertSame($statusCode, $this->commandTester->getStatusCode());
	}

	public function dataCommandInput(): array {
		return [
			[['admin_audit'], 0, 'admin_audit disabled'],
			[['comments'], 0, 'comments disabled'],
			[['invalid_app'], 0, 'No such app enabled: invalid_app'],

			[['admin_audit', 'comments'], 0, "admin_audit disabled\ncomments disabled"],
			[['admin_audit', 'comments', 'invalid_app'], 0, "admin_audit disabled\ncomments disabled\nNo such app enabled: invalid_app"],

			[['files'], 2, "files can't be disabled"],
			[['provisioning_api'], 2, "provisioning_api can't be disabled"],

			[['files', 'admin_audit'], 2, "files can't be disabled.\nadmin_audit disabled"],
			[['provisioning_api', 'comments'], 2, "provisioning_api can't be disabled.\ncomments disabled"],

		];
	}
}
