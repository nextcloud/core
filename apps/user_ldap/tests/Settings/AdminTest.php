<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\User_LDAP\Tests\Settings;

use OCA\User_LDAP\Configuration;
use OCA\User_LDAP\Settings\Admin;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\Template;
use Test\TestCase;

/**
 * @group DB
 * @package OCA\User_LDAP\Tests\Settings
 */
class AdminTest extends TestCase {
	/** @var Admin */
	private $admin;
	/** @var IL10N */
	private $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->l10n = $this->getMockBuilder(IL10N::class)->getMock();

		$this->admin = new Admin(
			$this->l10n
		);
	}

	/**
	 * @UseDB
	 */
	public function testGetForm() {
		$prefixes = ['s01'];
		$hosts = ['s01' => ''];

		$wControls = new Template('user_ldap', 'part.wizardcontrols');
		$wControls = $wControls->fetchPage();
		$sControls = new Template('user_ldap', 'part.settingcontrols');
		$sControls = $sControls->fetchPage();

		$parameters['serverConfigurationPrefixes'] = $prefixes;
		$parameters['serverConfigurationHosts'] = $hosts;
		$parameters['settingControls'] = $sControls;
		$parameters['wizardControls'] = $wControls;

		// assign default values
		$config = new Configuration('', false);
		$defaults = $config->getDefaults();
		foreach($defaults as $key => $default) {
			$parameters[$key.'_default'] = $default;
		}

		$expected = new TemplateResponse('user_ldap', 'settings', $parameters);
		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection() {
		$this->assertSame('ldap', $this->admin->getSection());
	}

	public function testGetPriority() {
		$this->assertSame(5, $this->admin->getPriority());
	}
}
