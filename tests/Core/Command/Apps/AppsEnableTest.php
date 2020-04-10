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

use OC\Core\Command\App\Enable;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

/**
 * Class AppsEnableTest
 *
 * @group DB
 */
class AppsEnableTest extends TestCase {

	/** @var CommandTester */
	private $commandTester;

	protected function setUp(): void {
		parent::setUp();

		$command = new Enable(
			\OC::$server->getAppManager(),
			\OC::$server->getGroupManager()
		);

		$this->commandTester = new CommandTester($command);

		\OC::$server->getAppManager()->disableApp('admin_audit');
		\OC::$server->getAppManager()->disableApp('comments');
	}

	/**
	 * @dataProvider dataCommandInput
	 * @param $appId
	 * @param $groups
	 * @param $statusCode
	 * @param $output
	 */
	public function testCommandInput($appId, $groups, $statusCode, $output): void {
		$input = ['app-id' => $appId];

		if (is_array($groups)) {
			$input['--groups'] = $groups;
		}

		$this->commandTester->execute($input);

		$this->assertStringContainsString($output, $this->commandTester->getDisplay());
		$this->assertSame($statusCode, $this->commandTester->getStatusCode());
	}

	public function dataCommandInput(): array {
		$data = [
			[['admin_audit'], null, 0, 'admin_audit enabled'],
			[['comments'], null, 0, 'comments enabled'],
			[['invalid_app'], null, 1, 'Could not download app invalid_app'],

			[['admin_audit', 'comments'], null, 0, "admin_audit enabled\ncomments enabled"],
			[['admin_audit', 'comments', 'invalid_app'], null, 1, "admin_audit enabled\ncomments enabled\nCould not download app invalid_app"],

			[['admin_audit'], ['admin'], 1, "admin_audit can't be enabled for groups"],
			[['comments'], ['admin'], 1, "comments can't be enabled for groups"],

			[['updatenotification'], ['admin'], 0, 'updatenotification enabled for groups: admin'],
			[['updatenotification', 'accessibility'], ['admin'], 0, "updatenotification enabled for groups: admin\naccessibility enabled for groups: admin"],

			[['updatenotification'], ['admin', 'invalid_group'], 0, 'updatenotification enabled for groups: admin'],
			[['updatenotification', 'accessibility'], ['admin', 'invalid_group'], 0, "updatenotification enabled for groups: admin\naccessibility enabled for groups: admin"],
			[['updatenotification', 'accessibility', 'invalid_app'], ['admin', 'invalid_group'], 1, "updatenotification enabled for groups: admin\naccessibility enabled for groups: admin\nCould not download app invalid_app"],
		];

		if (getenv('CI') === false) {
			/** Tests disabled on drone/ci due to appstore dependency */
			$data[] = [['updatenotification', 'contacts'], ['admin'], 0, "updatenotification enabled for groups: admin\ncontacts enabled for groups: admin"];
			$data[] = [['updatenotification', 'contacts'], ['admin', 'invalid_group'], 0, "updatenotification enabled for groups: admin\ncontacts enabled for groups: admin"];
			$data[] = [['updatenotification', 'contacts', 'invalid_app'], ['admin', 'invalid_group'], 1, "updatenotification enabled for groups: admin\ncontacts enabled for groups: admin\nCould not download app invalid_app"];
		}

		return $data;
	}
}
