<?php
/**
 * Copyright (c) 2012 Lukas Reschke <lukas@statuscode.ch>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OC_Util;
use OCP\App\IAppManager;
use OCP\IConfig;
use OCP\IUser;

/**
 * Class UtilTest
 *
 * @package Test
 * @group DB
 */
class UtilTest extends \Test\TestCase {
	public function testGetVersion() {
		$version = \OCP\Util::getVersion();
		$this->assertTrue(is_array($version));
		foreach ($version as $num) {
			$this->assertTrue(is_int($num));
		}
	}

	public function testGetVersionString() {
		$version = \OC_Util::getVersionString();
		$this->assertTrue(is_string($version));
	}

	public function testGetEditionString() {
		$edition = \OC_Util::getEditionString();
		$this->assertTrue(is_string($edition));
	}

	function testSanitizeHTML() {
		$badArray = [
			'While it is unusual to pass an array',
			'this function actually <blink>supports</blink> it.',
			'And therefore there needs to be a <script>alert("Unit"+\'test\')</script> for it!',
			[
				'And It Even May <strong>Nest</strong>',
			],
		];
		$goodArray = [
			'While it is unusual to pass an array',
			'this function actually &lt;blink&gt;supports&lt;/blink&gt; it.',
			'And therefore there needs to be a &lt;script&gt;alert(&quot;Unit&quot;+&#039;test&#039;)&lt;/script&gt; for it!',
			[
				'And It Even May &lt;strong&gt;Nest&lt;/strong&gt;'
			],
		];
		$result = OC_Util::sanitizeHTML($badArray);
		$this->assertEquals($goodArray, $result);

		$badString = '<img onload="alert(1)" />';
		$result = OC_Util::sanitizeHTML($badString);
		$this->assertEquals('&lt;img onload=&quot;alert(1)&quot; /&gt;', $result);

		$badString = "<script>alert('Hacked!');</script>";
		$result = OC_Util::sanitizeHTML($badString);
		$this->assertEquals('&lt;script&gt;alert(&#039;Hacked!&#039;);&lt;/script&gt;', $result);

		$goodString = 'This is a good string without HTML.';
		$result = OC_Util::sanitizeHTML($goodString);
		$this->assertEquals('This is a good string without HTML.', $result);
	}

	function testEncodePath() {
		$component = '/§#@test%&^ä/-child';
		$result = OC_Util::encodePath($component);
		$this->assertEquals("/%C2%A7%23%40test%25%26%5E%C3%A4/-child", $result);
	}

	public function testFileInfoLoaded() {
		$expected = function_exists('finfo_open');
		$this->assertEquals($expected, \OC_Util::fileInfoLoaded());
	}

	function testGetDefaultEmailAddress() {
		$email = \OCP\Util::getDefaultEmailAddress("no-reply");
		$this->assertEquals('no-reply@localhost', $email);
	}

	function testGetDefaultEmailAddressFromConfig() {
		$config = \OC::$server->getConfig();
		$config->setSystemValue('mail_domain', 'example.com');
		$email = \OCP\Util::getDefaultEmailAddress("no-reply");
		$this->assertEquals('no-reply@example.com', $email);
		$config->deleteSystemValue('mail_domain');
	}

	function testGetConfiguredEmailAddressFromConfig() {
		$config = \OC::$server->getConfig();
		$config->setSystemValue('mail_domain', 'example.com');
		$config->setSystemValue('mail_from_address', 'owncloud');
		$email = \OCP\Util::getDefaultEmailAddress("no-reply");
		$this->assertEquals('owncloud@example.com', $email);
		$config->deleteSystemValue('mail_domain');
		$config->deleteSystemValue('mail_from_address');
	}

	function testGetInstanceIdGeneratesValidId() {
		\OC::$server->getConfig()->deleteSystemValue('instanceid');
		$instanceId = OC_Util::getInstanceId();
		$this->assertStringStartsWith('oc', $instanceId);
		$matchesRegex = preg_match('/^[a-z0-9]+$/', $instanceId);
		$this->assertSame(1, $matchesRegex);
	}

	/**
	 * @dataProvider filenameValidationProvider
	 */
	public function testFilenameValidation($file, $valid) {
		// private API
		$this->assertEquals($valid, \OC_Util::isValidFileName($file));
		// public API
		$this->assertEquals($valid, \OCP\Util::isValidFileName($file));
	}

	public function filenameValidationProvider() {
		return [
			// valid names
			['boringname', true],
			['something.with.extension', true],
			['now with spaces', true],
			['.a', true],
			['..a', true],
			['.dotfile', true],
			['single\'quote', true],
			['  spaces before', true],
			['spaces after   ', true],
			['allowed chars including the crazy ones $%&_-^@!,()[]{}=;#', true],
			['汉字也能用', true],
			['und Ümläüte sind auch willkommen', true],
			// disallowed names
			['', false],
			['     ', false],
			['.', false],
			['..', false],
			['back\\slash', false],
			['sl/ash', false],
			['lt<lt', true],
			['gt>gt', true],
			['col:on', true],
			['double"quote', true],
			['pi|pe', true],
			['dont?ask?questions?', true],
			['super*star', true],
			['new\nline', false],

			// better disallow these to avoid unexpected trimming to have side effects
			[' ..', false],
			['.. ', false],
			['. ', false],
			[' .', false],

			// part files not allowed
			['.part', false],
			['notallowed.part', false],
			['neither.filepart', false],

			// part in the middle is ok
			['super movie part one.mkv', true],
			['super.movie.part.mkv', true],
		];
	}

	/**
	 * @dataProvider dataProviderForTestIsSharingDisabledForUser
	 * @param array $groups existing groups
	 * @param array $membership groups the user belong to
	 * @param array $excludedGroups groups which should be excluded from sharing
	 * @param bool $expected expected result
	 */
	function testIsSharingDisabledForUser($groups, $membership, $excludedGroups, $expected) {
		$config = $this->getMockBuilder(IConfig::class)->disableOriginalConstructor()->getMock();
		$groupManager = $this->getMockBuilder('OCP\IGroupManager')->disableOriginalConstructor()->getMock();
		$user = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();

		$config
				->expects($this->at(0))
				->method('getAppValue')
				->with('core', 'shareapi_exclude_groups', 'no')
				->willReturn('yes');
		$config
				->expects($this->at(1))
				->method('getAppValue')
				->with('core', 'shareapi_exclude_groups_list')
				->willReturn(json_encode($excludedGroups));

		$groupManager
				->expects($this->at(0))
				->method('getUserGroupIds')
				->with($user)
				->willReturn($membership);

		$result = \OC_Util::isSharingDisabledForUser($config, $groupManager, $user);

		$this->assertSame($expected, $result);
	}

	public function dataProviderForTestIsSharingDisabledForUser() {
		return [
			// existing groups, groups the user belong to, groups excluded from sharing, expected result
			[['g1', 'g2', 'g3'], [], ['g1'], false],
			[['g1', 'g2', 'g3'], [], [], false],
			[['g1', 'g2', 'g3'], ['g2'], ['g1'], false],
			[['g1', 'g2', 'g3'], ['g2'], [], false],
			[['g1', 'g2', 'g3'], ['g1', 'g2'], ['g1'], false],
			[['g1', 'g2', 'g3'], ['g1', 'g2'], ['g1', 'g2'], true],
			[['g1', 'g2', 'g3'], ['g1', 'g2'], ['g1', 'g2', 'g3'], true],
		];
	}

	/**
	 * Test default apps
	 *
	 * @dataProvider defaultAppsProvider
	 * @group DB
	 */
	function testDefaultApps($defaultAppConfig, $expectedPath, $enabledApps) {
		$oldDefaultApps = \OC::$server->getConfig()->getSystemValue('defaultapp', '');
		// CLI is doing messy stuff with the webroot, so need to work it around
		$oldWebRoot = \OC::$WEBROOT;
		\OC::$WEBROOT = '';

		$appManager = $this->createMock(IAppManager::class);
		$appManager->expects($this->any())
			->method('isEnabledForUser')
			->willReturnCallback(function ($appId) use ($enabledApps) {
				return in_array($appId, $enabledApps);
		});
		Dummy_OC_Util::$appManager = $appManager;

		// need to set a user id to make sure enabled apps are read from cache
		\OC_User::setUserId($this->getUniqueID());
		\OC::$server->getConfig()->setSystemValue('defaultapp', $defaultAppConfig);
		$this->assertEquals('http://localhost/' . $expectedPath, Dummy_OC_Util::getDefaultPageUrl());

		// restore old state
		\OC::$WEBROOT = $oldWebRoot;
		\OC::$server->getConfig()->setSystemValue('defaultapp', $oldDefaultApps);
		\OC_User::setUserId(null);
	}

	function defaultAppsProvider() {
		return [
			// none specified, default to files
			[
				'',
				'index.php/apps/files/',
				['files'],
			],
			// unexisting or inaccessible app specified, default to files
			[
				'unexist',
				'index.php/apps/files/',
				['files'],
			],
			// non-standard app
			[
				'calendar',
				'index.php/apps/calendar/',
				['files', 'calendar'],
			],
			// non-standard app with fallback
			[
				'contacts,calendar',
				'index.php/apps/calendar/',
				['files', 'calendar'],
			],
		];
	}

	public function testGetDefaultPageUrlWithRedirectUrlWithoutFrontController() {
		putenv('front_controller_active=false');
		\OC::$server->getConfig()->deleteSystemValue('htaccess.IgnoreFrontController');

		$_REQUEST['redirect_url'] = 'myRedirectUrl.com';
		$this->assertSame('http://localhost'.\OC::$WEBROOT.'/myRedirectUrl.com', OC_Util::getDefaultPageUrl());
	}

	public function testGetDefaultPageUrlWithRedirectUrlRedirectBypassWithoutFrontController() {
		putenv('front_controller_active=false');
		\OC::$server->getConfig()->deleteSystemValue('htaccess.IgnoreFrontController');

		$_REQUEST['redirect_url'] = 'myRedirectUrl.com@foo.com:a';
		$this->assertSame('http://localhost'.\OC::$WEBROOT.'/index.php/apps/files/', OC_Util::getDefaultPageUrl());
	}

	public function testGetDefaultPageUrlWithRedirectUrlRedirectBypassWithFrontController() {
		putenv('front_controller_active=true');
		$_REQUEST['redirect_url'] = 'myRedirectUrl.com@foo.com:a';
		$this->assertSame('http://localhost'.\OC::$WEBROOT.'/apps/files/', OC_Util::getDefaultPageUrl());
	}

	public function testGetDefaultPageUrlWithRedirectUrlWithIgnoreFrontController() {
		putenv('front_controller_active=false');
		\OC::$server->getConfig()->setSystemValue('htaccess.IgnoreFrontController', true);

		$_REQUEST['redirect_url'] = 'myRedirectUrl.com@foo.com:a';
		$this->assertSame('http://localhost'.\OC::$WEBROOT.'/apps/files/', OC_Util::getDefaultPageUrl());
	}

	/**
	 * Test needUpgrade() when the core version is increased
	 */
	public function testNeedUpgradeCore() {
		$config = \OC::$server->getConfig();
		$oldConfigVersion = $config->getSystemValue('version', '0.0.0');
		$oldSessionVersion = \OC::$server->getSession()->get('OC_Version');

		$this->assertFalse(\OCP\Util::needUpgrade());

		$config->setSystemValue('version', '7.0.0.0');
		\OC::$server->getSession()->set('OC_Version', [7, 0, 0, 1]);
		self::invokePrivate(new \OCP\Util, 'needUpgradeCache', [null]);

		$this->assertTrue(\OCP\Util::needUpgrade());

		$config->setSystemValue('version', $oldConfigVersion);
		\OC::$server->getSession()->set('OC_Version', $oldSessionVersion);
		self::invokePrivate(new \OCP\Util, 'needUpgradeCache', [null]);

		$this->assertFalse(\OCP\Util::needUpgrade());
	}

	public function testCheckDataDirectoryValidity() {
		$dataDir = \OC::$server->getTempManager()->getTemporaryFolder();
		touch($dataDir . '/.ocdata');
		$errors = \OC_Util::checkDataDirectoryValidity($dataDir);
		$this->assertEmpty($errors);
		\OCP\Files::rmdirr($dataDir);

		$dataDir = \OC::$server->getTempManager()->getTemporaryFolder();
		// no touch
		$errors = \OC_Util::checkDataDirectoryValidity($dataDir);
		$this->assertNotEmpty($errors);
		\OCP\Files::rmdirr($dataDir);

		$errors = \OC_Util::checkDataDirectoryValidity('relative/path');
		$this->assertNotEmpty($errors);
	}

	protected function setUp(): void {
		parent::setUp();

		\OC_Util::$scripts = [];
		\OC_Util::$styles = [];
	}
	protected function tearDown(): void {
		parent::tearDown();

		\OC_Util::$scripts = [];
		\OC_Util::$styles = [];
	}

	public function testAddScript() {
		\OC_Util::addScript('core', 'myFancyJSFile1');
		\OC_Util::addScript('myApp', 'myFancyJSFile2');
		\OC_Util::addScript('core', 'myFancyJSFile0', true);
		\OC_Util::addScript('core', 'myFancyJSFile10', true);
		// add duplicate
		\OC_Util::addScript('core', 'myFancyJSFile1');

		$this->assertEquals([
			'core/js/myFancyJSFile10',
			'core/js/myFancyJSFile0',
			'core/js/myFancyJSFile1',
			'myApp/l10n/en',
			'myApp/js/myFancyJSFile2',
		], \OC_Util::$scripts);
		$this->assertEquals([], \OC_Util::$styles);
	}

	public function testAddVendorScript() {
		\OC_Util::addVendorScript('core', 'myFancyJSFile1');
		\OC_Util::addVendorScript('myApp', 'myFancyJSFile2');
		\OC_Util::addVendorScript('core', 'myFancyJSFile0', true);
		\OC_Util::addVendorScript('core', 'myFancyJSFile10', true);
		// add duplicate
		\OC_Util::addVendorScript('core', 'myFancyJSFile1');

		$this->assertEquals([
			'core/vendor/myFancyJSFile10',
			'core/vendor/myFancyJSFile0',
			'core/vendor/myFancyJSFile1',
			'myApp/vendor/myFancyJSFile2',
		], \OC_Util::$scripts);
		$this->assertEquals([], \OC_Util::$styles);
	}

	public function testAddTranslations() {
		\OC_Util::addTranslations('appId', 'de');

		$this->assertEquals([
			'appId/l10n/de'
		], \OC_Util::$scripts);
		$this->assertEquals([], \OC_Util::$styles);
	}

	public function testAddStyle() {
		\OC_Util::addStyle('core', 'myFancyCSSFile1');
		\OC_Util::addStyle('myApp', 'myFancyCSSFile2');
		\OC_Util::addStyle('core', 'myFancyCSSFile0', true);
		\OC_Util::addStyle('core', 'myFancyCSSFile10', true);
		// add duplicate
		\OC_Util::addStyle('core', 'myFancyCSSFile1');

		$this->assertEquals([], \OC_Util::$scripts);
		$this->assertEquals([
			'core/css/myFancyCSSFile10',
			'core/css/myFancyCSSFile0',
			'core/css/myFancyCSSFile1',
			'myApp/css/myFancyCSSFile2',
		], \OC_Util::$styles);
	}

	public function testAddVendorStyle() {
		\OC_Util::addVendorStyle('core', 'myFancyCSSFile1');
		\OC_Util::addVendorStyle('myApp', 'myFancyCSSFile2');
		\OC_Util::addVendorStyle('core', 'myFancyCSSFile0', true);
		\OC_Util::addVendorStyle('core', 'myFancyCSSFile10', true);
		// add duplicate
		\OC_Util::addVendorStyle('core', 'myFancyCSSFile1');

		$this->assertEquals([], \OC_Util::$scripts);
		$this->assertEquals([
			'core/vendor/myFancyCSSFile10',
			'core/vendor/myFancyCSSFile0',
			'core/vendor/myFancyCSSFile1',
			'myApp/vendor/myFancyCSSFile2',
		], \OC_Util::$styles);
	}
}

/**
 * Dummy OC Util class to make it possible to override the app manager
 */
class Dummy_OC_Util extends OC_Util {
	/**
	 * @var \OCP\App\IAppManager
	 */
	public static $appManager;

	protected static function getAppManager() {
		return self::$appManager;
	}
}
