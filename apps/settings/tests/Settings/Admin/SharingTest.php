<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Settings\Tests\Settings\Admin;

use OCA\Settings\Settings\Admin\Sharing;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Constants;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Share\IManager;
use Test\TestCase;

class SharingTest extends TestCase {
	/** @var Sharing */
	private $admin;
	/** @var IConfig */
	private $config;
	/** @var  IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l10n;
	/** @var  IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
		$this->l10n = $this->getMockBuilder(IL10N::class)->getMock();

		$this->shareManager = $this->getMockBuilder(IManager::class)->getMock();

		$this->admin = new Sharing(
			$this->config,
			$this->l10n,
			$this->shareManager
		);
	}

	public function testGetFormWithoutExcludedGroups() {
		$this->config
			->expects($this->at(0))
			->method('getAppValue')
			->with('core', 'shareapi_exclude_groups_list', '')
			->willReturn('');
		$this->config
			->expects($this->at(1))
			->method('getAppValue')
			->with('core', 'shareapi_allow_group_sharing', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(2))
			->method('getAppValue')
			->with('core', 'shareapi_allow_links', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(3))
			->method('getAppValue')
			->with('core', 'shareapi_allow_public_upload', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(4))
			->method('getAppValue')
			->with('core', 'shareapi_allow_resharing', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(5))
			->method('getAppValue')
			->with('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(6))
			->method('getAppValue')
			->with('core', 'shareapi_restrict_user_enumeration_to_group', 'no')
			->willReturn('no');
		$this->config
			->expects($this->at(7))
			->method('getAppValue')
			->with('core', 'shareapi_enabled', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(8))
			->method('getAppValue')
			->with('core', 'shareapi_default_expire_date', 'no')
			->willReturn('no');
		$this->config
			->expects($this->at(9))
			->method('getAppValue')
			->with('core', 'shareapi_expire_after_n_days', '7')
			->willReturn('7');
		$this->config
			->expects($this->at(10))
			->method('getAppValue')
			->with('core', 'shareapi_enforce_expire_date', 'no')
			->willReturn('no');
		$this->config
			->expects($this->at(11))
			->method('getAppValue')
			->with('core', 'shareapi_exclude_groups', 'no')
			->willReturn('no');
		$this->config
			->expects($this->at(12))
			->method('getAppValue')
			->with('core', 'shareapi_public_link_disclaimertext', null)
			->willReturn('Lorem ipsum');
		$this->config
			->expects($this->at(13))
			->method('getAppValue')
			->with('core', 'shareapi_enable_link_password_by_default', 'no')
			->willReturn('yes');
		$this->config
			->expects($this->at(14))
			->method('getAppValue')
			->with('core', 'shareapi_default_permissions', Constants::PERMISSION_ALL)
			->willReturn(Constants::PERMISSION_ALL);
		$this->config
			->expects($this->at(15))
			->method('getAppValue')
			->with('core', 'shareapi_default_internal_expire_date', 'no')
			->willReturn('no');
		$this->config
			->expects($this->at(16))
			->method('getAppValue')
			->with('core', 'shareapi_internal_expire_after_n_days', '7')
			->willReturn('7');
		$this->config
			->expects($this->at(17))
			->method('getAppValue')
			->with('core', 'shareapi_enforce_internal_expire_date', 'no')
			->willReturn('no');

		$expected = new TemplateResponse(
			'settings',
			'settings/admin/sharing',
			[
				'allowGroupSharing'               => 'yes',
				'allowLinks'                      => 'yes',
				'allowPublicUpload'               => 'yes',
				'allowResharing'                  => 'yes',
				'allowShareDialogUserEnumeration' => 'yes',
				'restrictUserEnumerationToGroup'  => 'no',
				'enforceLinkPassword'             => false,
				'onlyShareWithGroupMembers'       => false,
				'shareAPIEnabled'                 => 'yes',
				'shareDefaultExpireDateSet'       => 'no',
				'shareExpireAfterNDays'           => '7',
				'shareEnforceExpireDate'          => 'no',
				'shareExcludeGroups'              => false,
				'shareExcludedGroupsList'         => '',
				'publicShareDisclaimerText'       => 'Lorem ipsum',
				'enableLinkPasswordByDefault'     => 'yes',
				'shareApiDefaultPermissions'      => Constants::PERMISSION_ALL,
				'shareApiDefaultPermissionsCheckboxes' => $this->invokePrivate($this->admin, 'getSharePermissionList', []),
				'shareDefaultInternalExpireDateSet' => 'no',
				'shareInternalExpireAfterNDays' => '7',
				'shareInternalEnforceExpireDate' => 'no',
			],
			''
		);

		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetFormWithExcludedGroups() {
		$this->config
			->expects($this->at(0))
			->method('getAppValue')
			->with('core', 'shareapi_exclude_groups_list', '')
			->willReturn('["NoSharers","OtherNoSharers"]');
		$this->config
			->expects($this->at(1))
			->method('getAppValue')
			->with('core', 'shareapi_allow_group_sharing', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(2))
			->method('getAppValue')
			->with('core', 'shareapi_allow_links', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(3))
			->method('getAppValue')
			->with('core', 'shareapi_allow_public_upload', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(4))
			->method('getAppValue')
			->with('core', 'shareapi_allow_resharing', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(5))
			->method('getAppValue')
			->with('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(6))
			->method('getAppValue')
			->with('core', 'shareapi_restrict_user_enumeration_to_group', 'no')
			->willReturn('no');
		$this->config
			->expects($this->at(7))
			->method('getAppValue')
			->with('core', 'shareapi_enabled', 'yes')
			->willReturn('yes');
		$this->config
			->expects($this->at(8))
			->method('getAppValue')
			->with('core', 'shareapi_default_expire_date', 'no')
			->willReturn('no');
		$this->config
			->expects($this->at(9))
			->method('getAppValue')
			->with('core', 'shareapi_expire_after_n_days', '7')
			->willReturn('7');
		$this->config
			->expects($this->at(10))
			->method('getAppValue')
			->with('core', 'shareapi_enforce_expire_date', 'no')
			->willReturn('no');
		$this->config
			->expects($this->at(11))
			->method('getAppValue')
			->with('core', 'shareapi_exclude_groups', 'no')
			->willReturn('yes');
		$this->config
			->expects($this->at(12))
			->method('getAppValue')
			->with('core', 'shareapi_public_link_disclaimertext', null)
			->willReturn('Lorem ipsum');
		$this->config
			->expects($this->at(13))
			->method('getAppValue')
			->with('core', 'shareapi_enable_link_password_by_default', 'no')
			->willReturn('yes');
		$this->config
			->expects($this->at(14))
			->method('getAppValue')
			->with('core', 'shareapi_default_permissions', Constants::PERMISSION_ALL)
			->willReturn(Constants::PERMISSION_ALL);
		$this->config
			->expects($this->at(15))
			->method('getAppValue')
			->with('core', 'shareapi_default_internal_expire_date', 'no')
			->willReturn('no');
		$this->config
			->expects($this->at(16))
			->method('getAppValue')
			->with('core', 'shareapi_internal_expire_after_n_days', '7')
			->willReturn('7');
		$this->config
			->expects($this->at(17))
			->method('getAppValue')
			->with('core', 'shareapi_enforce_internal_expire_date', 'no')
			->willReturn('no');


		$expected = new TemplateResponse(
			'settings',
			'settings/admin/sharing',
			[
				'allowGroupSharing'               => 'yes',
				'allowLinks'                      => 'yes',
				'allowPublicUpload'               => 'yes',
				'allowResharing'                  => 'yes',
				'allowShareDialogUserEnumeration' => 'yes',
				'restrictUserEnumerationToGroup'  => 'no',
				'enforceLinkPassword'             => false,
				'onlyShareWithGroupMembers'       => false,
				'shareAPIEnabled'                 => 'yes',
				'shareDefaultExpireDateSet'       => 'no',
				'shareExpireAfterNDays'           => '7',
				'shareEnforceExpireDate'          => 'no',
				'shareExcludeGroups'              => true,
				'shareExcludedGroupsList'         => 'NoSharers|OtherNoSharers',
				'publicShareDisclaimerText'       => 'Lorem ipsum',
				'enableLinkPasswordByDefault'     => 'yes',
				'shareApiDefaultPermissions'      => Constants::PERMISSION_ALL,
				'shareApiDefaultPermissionsCheckboxes' => $this->invokePrivate($this->admin, 'getSharePermissionList', []),
				'shareDefaultInternalExpireDateSet' => 'no',
				'shareInternalExpireAfterNDays' => '7',
				'shareInternalEnforceExpireDate' => 'no',
			],
			''
		);

		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection() {
		$this->assertSame('sharing', $this->admin->getSection());
	}

	public function testGetPriority() {
		$this->assertSame(0, $this->admin->getPriority());
	}
}
