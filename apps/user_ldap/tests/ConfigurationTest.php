<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\User_LDAP\Tests;

use OCA\User_LDAP\Configuration;

class ConfigurationTest extends \Test\TestCase {
	/** @var Configuration */
	protected $configuration;

	protected function setUp(): void {
		parent::setUp();
		$this->configuration = new Configuration('t01', false);
	}

	public function configurationDataProvider() {
		$inputWithDN = [
			'cn=someUsers,dc=example,dc=org',
			'  ',
			' cn=moreUsers,dc=example,dc=org '
		];
		$expectWithDN = [
			'cn=someUsers,dc=example,dc=org',
			'cn=moreUsers,dc=example,dc=org'
		];

		$inputNames = [
			'  uid  ',
			'cn ',
			' ',
			'',
			' whats my name',
			'	'
		];
		$expectedNames = ['uid', 'cn', 'whats my name'];

		$inputString = ' alea iacta est ';
		$expectedString = 'alea iacta est';

		$inputHomeFolder = [
			' homeDirectory ',
			' attr:homeDirectory ',
			' '
		];

		$expectedHomeFolder = [
			'attr:homeDirectory', 'attr:homeDirectory', ''
		];

		$password = ' such a passw0rd ';

		return [
			'set general base' => ['ldapBase', $inputWithDN, $expectWithDN],
			'set user base' => ['ldapBaseUsers', $inputWithDN, $expectWithDN],
			'set group base' => ['ldapBaseGroups', $inputWithDN, $expectWithDN],

			'set search attributes users' => ['ldapAttributesForUserSearch', $inputNames, $expectedNames],
			'set search attributes groups' => ['ldapAttributesForGroupSearch', $inputNames, $expectedNames],

			'set user filter objectclasses' => ['ldapUserFilterObjectclass', $inputNames, $expectedNames],
			'set user filter groups' => ['ldapUserFilterGroups', $inputNames, $expectedNames],
			'set group filter objectclasses' => ['ldapGroupFilterObjectclass', $inputNames, $expectedNames],
			'set group filter groups' => ['ldapGroupFilterGroups', $inputNames, $expectedNames],
			'set login filter attributes' => ['ldapLoginFilterAttributes', $inputNames, $expectedNames],

			'set agent password' => ['ldapAgentPassword', $password, $password],

			'set home folder, variant 1' => ['homeFolderNamingRule', $inputHomeFolder[0], $expectedHomeFolder[0]],
			'set home folder, variant 2' => ['homeFolderNamingRule', $inputHomeFolder[1], $expectedHomeFolder[1]],
			'set home folder, empty' => ['homeFolderNamingRule', $inputHomeFolder[2], $expectedHomeFolder[2]],

			// default behaviour, one case is enough, special needs must be tested
			// individually
			'set string value' => ['ldapHost', $inputString, $expectedString],

			'set avatar rule, default' => ['ldapUserAvatarRule', 'default', 'default'],
			'set avatar rule, none' => ['ldapUserAvatarRule', 'none', 'none'],
			'set avatar rule, data attribute' => ['ldapUserAvatarRule', 'data:jpegPhoto', 'data:jpegPhoto'],

			'set external storage home attribute' => ['ldapExtStorageHomeAttribute', 'homePath', 'homePath'],
		];
	}

	/**
	 * @dataProvider configurationDataProvider
	 */
	public function testSetValue($key, $input, $expected) {
		$this->configuration->setConfiguration([$key => $input]);
		$this->assertSame($this->configuration->$key, $expected);
	}

	public function avatarRuleValueProvider() {
		return [
			['none', []],
			['data:selfie', ['selfie']],
			['data:sELFie', ['selfie']],
			['data:', ['jpegphoto', 'thumbnailphoto']],
			['default', ['jpegphoto', 'thumbnailphoto']],
			['invalid#', ['jpegphoto', 'thumbnailphoto']],
		];
	}

	/**
	 * @dataProvider avatarRuleValueProvider
	 */
	public function testGetAvatarAttributes($setting, $expected) {
		$this->configuration->setConfiguration(['ldapUserAvatarRule' => $setting]);
		$this->assertSame($expected, $this->configuration->getAvatarAttributes());
	}

	/**
	 * @dataProvider avatarRuleValueProvider
	 */
	public function testResolveRule($setting, $expected) {
		$this->configuration->setConfiguration(['ldapUserAvatarRule' => $setting]);
		// so far the only thing that can get resolved :)
		$this->assertSame($expected, $this->configuration->resolveRule('avatar'));
	}
}
