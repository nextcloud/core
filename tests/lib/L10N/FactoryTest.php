<?php
/**
 * Copyright (c) 2016 Joas Schilling <nickvergessen@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\L10N;

use OC\L10N\Factory;
use OC\L10N\LanguageNotFoundException;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\ILanguageIterator;
use Test\TestCase;

/**
 * Class FactoryTest
 *
 * @package Test\L10N
 */
class FactoryTest extends TestCase {

	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;

	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	protected $request;

	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	protected $userSession;

	/** @var string */
	protected $serverRoot;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();

		$this->request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();

		$this->userSession = $this->getMockBuilder(IUserSession::class)
			->disableOriginalConstructor()
			->getMock();

		$this->serverRoot = \OC::$SERVERROOT;
	}

	/**
	 * @param array $methods
	 * @param bool $mockRequestGetHeaderMethod
	 * @return Factory|\PHPUnit\Framework\MockObject\MockObject
	 */
	protected function getFactory(array $methods = [], $mockRequestGetHeaderMethod = false) {
		if ($mockRequestGetHeaderMethod) {
			$this->request->expects($this->any())
				->method('getHeader')
				->willReturn('');
		}

		if (!empty($methods)) {
			return $this->getMockBuilder(Factory::class)
				->setConstructorArgs([
					$this->config,
					$this->request,
					$this->userSession,
					$this->serverRoot,
				])
				->setMethods($methods)
				->getMock();
		} else {
			return new Factory($this->config, $this->request, $this->userSession, $this->serverRoot);
		}
	}

	public function dataFindAvailableLanguages() {
		return [
			[null],
			['files'],
		];
	}

	public function testFindLanguageWithExistingRequestLanguageAndNoApp() {
		$factory = $this->getFactory(['languageExists']);
		$this->invokePrivate($factory, 'requestLanguage', ['de']);
		$factory->expects($this->once())
			->method('languageExists')
			->with(null, 'de')
			->willReturn(true);

		$this->assertSame('de', $factory->findLanguage());
	}

	public function testFindLanguageWithExistingRequestLanguageAndApp() {
		$factory = $this->getFactory(['languageExists']);
		$this->invokePrivate($factory, 'requestLanguage', ['de']);
		$factory->expects($this->once())
				->method('languageExists')
				->with('MyApp', 'de')
				->willReturn(true);

		$this->assertSame('de', $factory->findLanguage('MyApp'));
	}

	public function testFindLanguageWithNotExistingRequestLanguageAndExistingStoredUserLanguage() {
		$factory = $this->getFactory(['languageExists']);
		$this->invokePrivate($factory, 'requestLanguage', ['de']);
		$factory->expects($this->at(0))
				->method('languageExists')
				->with('MyApp', 'de')
				->willReturn(false);
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('force_language', false)
			->willReturn(false);
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('installed', false)
			->willReturn(true);
		$user = $this->getMockBuilder(IUser::class)
			->getMock();
		$user->expects($this->once())
			->method('getUID')
			->willReturn('MyUserUid');
		$this->userSession
			->expects($this->exactly(2))
			->method('getUser')
			->willReturn($user);
		$this->config
				->expects($this->once())
				->method('getUserValue')
				->with('MyUserUid', 'core', 'lang', null)
				->willReturn('jp');
		$factory->expects($this->at(1))
				->method('languageExists')
				->with('MyApp', 'jp')
				->willReturn(true);

		$this->assertSame('jp', $factory->findLanguage('MyApp'));
	}

	public function testFindLanguageWithNotExistingRequestLanguageAndNotExistingStoredUserLanguage() {
		$factory = $this->getFactory(['languageExists'], true);
		$this->invokePrivate($factory, 'requestLanguage', ['de']);
		$factory->expects($this->at(0))
				->method('languageExists')
				->with('MyApp', 'de')
				->willReturn(false);
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('force_language', false)
			->willReturn(false);
		$this->config
				->expects($this->at(1))
				->method('getSystemValue')
				->with('installed', false)
				->willReturn(true);
		$user = $this->getMockBuilder(IUser::class)
			->getMock();
		$user->expects($this->once())
				->method('getUID')
				->willReturn('MyUserUid');
		$this->userSession
				->expects($this->exactly(2))
				->method('getUser')
				->willReturn($user);
		$this->config
				->expects($this->once())
				->method('getUserValue')
				->with('MyUserUid', 'core', 'lang', null)
				->willReturn('jp');
		$factory->expects($this->at(1))
				->method('languageExists')
				->with('MyApp', 'jp')
				->willReturn(false);
		$this->config
				->expects($this->at(3))
				->method('getSystemValue')
				->with('default_language', false)
				->willReturn('es');
		$factory->expects($this->at(2))
				->method('languageExists')
				->with('MyApp', 'es')
				->willReturn(true);

		$this->assertSame('es', $factory->findLanguage('MyApp'));
	}

	public function testFindLanguageWithNotExistingRequestLanguageAndNotExistingStoredUserLanguageAndNotExistingDefault() {
		$factory = $this->getFactory(['languageExists'], true);
		$this->invokePrivate($factory, 'requestLanguage', ['de']);
		$factory->expects($this->at(0))
				->method('languageExists')
				->with('MyApp', 'de')
				->willReturn(false);
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('force_language', false)
			->willReturn(false);
		$this->config
				->expects($this->at(1))
				->method('getSystemValue')
				->with('installed', false)
				->willReturn(true);
		$user = $this->getMockBuilder(IUser::class)
			->getMock();
		$user->expects($this->once())
				->method('getUID')
				->willReturn('MyUserUid');
		$this->userSession
				->expects($this->exactly(2))
				->method('getUser')
				->willReturn($user);
		$this->config
				->expects($this->once())
				->method('getUserValue')
				->with('MyUserUid', 'core', 'lang', null)
				->willReturn('jp');
		$factory->expects($this->at(1))
				->method('languageExists')
				->with('MyApp', 'jp')
				->willReturn(false);
		$this->config
				->expects($this->at(3))
				->method('getSystemValue')
				->with('default_language', false)
				->willReturn('es');
		$factory->expects($this->at(2))
				->method('languageExists')
				->with('MyApp', 'es')
				->willReturn(false);
		$this->config
			->expects($this->never())
			->method('setUserValue');

		$this->assertSame('en', $factory->findLanguage('MyApp'));
	}

	public function testFindLanguageWithNotExistingRequestLanguageAndNotExistingStoredUserLanguageAndNotExistingDefaultAndNoAppInScope() {
		$factory = $this->getFactory(['languageExists'], true);
		$this->invokePrivate($factory, 'requestLanguage', ['de']);
		$factory->expects($this->at(0))
				->method('languageExists')
				->with('MyApp', 'de')
				->willReturn(false);
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('force_language', false)
			->willReturn(false);
		$this->config
				->expects($this->at(1))
				->method('getSystemValue')
				->with('installed', false)
				->willReturn(true);
		$user = $this->getMockBuilder(IUser::class)
			->getMock();
		$user->expects($this->once())
				->method('getUID')
				->willReturn('MyUserUid');
		$this->userSession
				->expects($this->exactly(2))
				->method('getUser')
				->willReturn($user);
		$this->config
				->expects($this->once())
				->method('getUserValue')
				->with('MyUserUid', 'core', 'lang', null)
				->willReturn('jp');
		$factory->expects($this->at(1))
				->method('languageExists')
				->with('MyApp', 'jp')
				->willReturn(false);
		$this->config
				->expects($this->at(3))
				->method('getSystemValue')
				->with('default_language', false)
				->willReturn('es');
		$factory->expects($this->at(2))
				->method('languageExists')
				->with('MyApp', 'es')
				->willReturn(false);
		$this->config
				->expects($this->never())
				->method('setUserValue')
				->with('MyUserUid', 'core', 'lang', 'en');


		$this->assertSame('en', $factory->findLanguage('MyApp'));
	}

	public function testFindLanguageWithForcedLanguage() {
		$factory = $this->getFactory(['languageExists']);
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('force_language', false)
			->willReturn('de');

		$factory->expects($this->once())
			->method('languageExists')
			->with('MyApp', 'de')
			->willReturn(true);

		$this->assertSame('de', $factory->findLanguage('MyApp'));
	}

	/**
	 * @dataProvider dataFindAvailableLanguages
	 *
	 * @param string|null $app
	 */
	public function testFindAvailableLanguages($app) {
		$factory = $this->getFactory(['findL10nDir']);
		$factory->expects($this->once())
			->method('findL10nDir')
			->with($app)
			->willReturn(\OC::$SERVERROOT . '/tests/data/l10n/');

		$this->assertEqualsCanonicalizing(['cs', 'de', 'en', 'ru'], $factory->findAvailableLanguages($app));
	}

	public function dataLanguageExists() {
		return [
			[null, 'en', [], true],
			[null, 'de', [], false],
			[null, 'de', ['ru'], false],
			[null, 'de', ['ru', 'de'], true],
			['files', 'en', [], true],
			['files', 'de', [], false],
			['files', 'de', ['ru'], false],
			['files', 'de', ['de', 'ru'], true],
		];
	}

	public function testFindAvailableLanguagesWithThemes() {
		$this->serverRoot .= '/tests/data';
		$app = 'files';

		$factory = $this->getFactory(['findL10nDir']);
		$factory->expects($this->once())
			->method('findL10nDir')
			->with($app)
			->willReturn($this->serverRoot . '/apps/files/l10n/');
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('theme')
			->willReturn('abc');

		$this->assertEqualsCanonicalizing(['en', 'zz'], $factory->findAvailableLanguages($app));
	}

	/**
	 * @dataProvider dataLanguageExists
	 *
	 * @param string|null $app
	 * @param string $lang
	 * @param string[] $availableLanguages
	 * @param string $expected
	 */
	public function testLanguageExists($app, $lang, array $availableLanguages, $expected) {
		$factory = $this->getFactory(['findAvailableLanguages']);
		$factory->expects(($lang === 'en') ? $this->never() : $this->once())
			->method('findAvailableLanguages')
			->with($app)
			->willReturn($availableLanguages);

		$this->assertSame($expected, $factory->languageExists($app, $lang));
	}

	public function dataSetLanguageFromRequest() {
		return [
			// Language is available
			[null, 'de', ['de'], 'de'],
			[null, 'de,en', ['de'], 'de'],
			[null, 'de-DE,en-US;q=0.8,en;q=0.6', ['de'], 'de'],
			// Language is not available
			[null, 'de', ['ru'], new LanguageNotFoundException()],
			[null, 'de,en', ['ru', 'en'], 'en'],
			[null, 'de-DE,en-US;q=0.8,en;q=0.6', ['ru', 'en'], 'en'],

			// Language for app
			['files_pdfviewer', 'de', ['de'], 'de'],
			['files_pdfviewer', 'de,en', ['de'], 'de'],
			['files_pdfviewer', 'de-DE,en-US;q=0.8,en;q=0.6', ['de'], 'de'],
			// Language for app is not available
			['files_pdfviewer', 'de', ['ru'], new LanguageNotFoundException()],
			['files_pdfviewer', 'de,en', ['ru', 'en'], 'en'],
			['files_pdfviewer', 'de-DE,en-US;q=0.8,en;q=0.6', ['ru', 'en'], 'en'],
		];
	}

	/**
	 * @dataProvider dataSetLanguageFromRequest
	 *
	 * @param string|null $app
	 * @param string $header
	 * @param string[] $availableLanguages
	 * @param string $expected
	 */
	public function testGetLanguageFromRequest($app, $header, array $availableLanguages, $expected) {
		$factory = $this->getFactory(['findAvailableLanguages', 'respectDefaultLanguage']);
		$factory->expects($this->once())
			->method('findAvailableLanguages')
			->with($app)
			->willReturn($availableLanguages);

		$factory->expects($this->any())
			->method('respectDefaultLanguage')->willReturnCallback(function ($app, $lang) {
				return $lang;
			});

		$this->request->expects($this->once())
			->method('getHeader')
			->with('ACCEPT_LANGUAGE')
			->willReturn($header);

		if ($expected instanceof LanguageNotFoundException) {
			$this->expectException(LanguageNotFoundException::class);
			self::invokePrivate($factory, 'getLanguageFromRequest', [$app]);
		} else {
			$this->assertSame($expected, self::invokePrivate($factory, 'getLanguageFromRequest', [$app]), 'Asserting returned language');
		}
	}

	public function dataGetL10nFilesForApp() {
		return [
			[null, 'de', [\OC::$SERVERROOT . '/core/l10n/de.json']],
			['core', 'ru', [\OC::$SERVERROOT . '/core/l10n/ru.json']],
			['lib', 'ru', [\OC::$SERVERROOT . '/lib/l10n/ru.json']],
			['settings', 'de', [\OC::$SERVERROOT . '/apps/settings/l10n/de.json']],
			['files', 'de', [\OC::$SERVERROOT . '/apps/files/l10n/de.json']],
			['files', '_lang_never_exists_', []],
			['_app_never_exists_', 'de', [\OC::$SERVERROOT . '/core/l10n/de.json']],
		];
	}

	/**
	 * @dataProvider dataGetL10nFilesForApp
	 *
	 * @param string|null $app
	 * @param string $expected
	 */
	public function testGetL10nFilesForApp($app, $lang, $expected) {
		$factory = $this->getFactory();
		$this->assertSame($expected, $this->invokePrivate($factory, 'getL10nFilesForApp', [$app, $lang]));
	}

	public function dataFindL10NDir() {
		return [
			[null, \OC::$SERVERROOT . '/core/l10n/'],
			['core', \OC::$SERVERROOT . '/core/l10n/'],
			['lib', \OC::$SERVERROOT . '/lib/l10n/'],
			['settings', \OC::$SERVERROOT . '/apps/settings/l10n/'],
			['files', \OC::$SERVERROOT . '/apps/files/l10n/'],
			['_app_never_exists_', \OC::$SERVERROOT . '/core/l10n/'],
		];
	}

	/**
	 * @dataProvider dataFindL10NDir
	 *
	 * @param string|null $app
	 * @param string $expected
	 */
	public function testFindL10NDir($app, $expected) {
		$factory = $this->getFactory();
		$this->assertSame($expected, $this->invokePrivate($factory, 'findL10nDir', [$app]));
	}

	public function dataFindLanguage() {
		return [
			// Not logged in
			[false, [], 'en'],
			[false, ['fr'], 'fr'],
			[false, ['de', 'fr'], 'de'],
			[false, ['nl', 'de', 'fr'], 'de'],

			[true, [], 'en'],
			[true, ['fr'], 'fr'],
			[true, ['de', 'fr'], 'de'],
			[true, ['nl', 'de', 'fr'], 'nl'],
		];
	}

	/**
	 * @dataProvider dataFindLanguage
	 *
	 * @param bool $loggedIn
	 * @param array $availableLang
	 * @param string $expected
	 */
	public function testFindLanguage($loggedIn, $availableLang, $expected) {
		$userLang = 'nl';
		$browserLang = 'de';
		$defaultLang = 'fr';

		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturnCallback(function ($var, $default) use ($defaultLang) {
				if ($var === 'installed') {
					return true;
				} elseif ($var === 'default_language') {
					return $defaultLang;
				} else {
					return $default;
				}
			});

		if ($loggedIn) {
			$user = $this->getMockBuilder(IUser::class)
				->getMock();
			$user->expects($this->any())
				->method('getUID')
				->willReturn('MyUserUid');
			$this->userSession
				->expects($this->any())
				->method('getUser')
				->willReturn($user);
			$this->config->expects($this->any())
				->method('getUserValue')
				->with('MyUserUid', 'core', 'lang', null)
				->willReturn($userLang);
		} else {
			$this->userSession
				->expects($this->any())
				->method('getUser')
				->willReturn(null);
		}

		$this->request->expects($this->any())
			->method('getHeader')
			->with($this->equalTo('ACCEPT_LANGUAGE'))
			->willReturn($browserLang);

		$factory = $this->getFactory(['languageExists', 'findAvailableLanguages', 'respectDefaultLanguage']);
		$factory->expects($this->any())
			->method('languageExists')
			->willReturnCallback(function ($app, $lang) use ($availableLang) {
				return in_array($lang, $availableLang);
			});
		$factory->expects($this->any())
			->method('findAvailableLanguages')
			->willReturnCallback(function ($app) use ($availableLang) {
				return $availableLang;
			});
		$factory->expects($this->any())
			->method('respectDefaultLanguage')->willReturnCallback(function ($app, $lang) {
				return $lang;
			});

		$lang = $factory->findLanguage(null);
		$this->assertSame($expected, $lang);
	}

	public function dataTestRespectDefaultLanguage() {
		return [
			['de', 'de_DE', true, 'de_DE'],
			['de', 'de', true, 'de'],
			['de', false, true, 'de'],
			['fr', 'de_DE', true, 'fr'],
		];
	}

	/**
	 * test if we respect default language if possible
	 *
	 * @dataProvider dataTestRespectDefaultLanguage
	 *
	 * @param string $lang
	 * @param string $defaultLanguage
	 * @param bool $langExists
	 * @param string $expected
	 */
	public function testRespectDefaultLanguage($lang, $defaultLanguage, $langExists, $expected) {
		$factory = $this->getFactory(['languageExists']);
		$factory->expects($this->any())
			->method('languageExists')->willReturn($langExists);
		$this->config->expects($this->any())
			->method('getSystemValue')->with('default_language', false)->willReturn($defaultLanguage);

		$result = $this->invokePrivate($factory, 'respectDefaultLanguage', ['app', $lang]);
		$this->assertSame($expected, $result);
	}

	public function languageIteratorRequestProvider():array {
		return [
			[ true, $this->createMock(IUser::class)],
			[ false, $this->createMock(IUser::class)],
			[ false, null]
		];
	}

	/**
	 * @dataProvider languageIteratorRequestProvider
	 */
	public function testGetLanguageIterator(bool $hasSession, IUser $iUserMock = null) {
		$factory = $this->getFactory();

		if ($iUserMock === null) {
			$matcher = $this->userSession->expects($this->once())
				->method('getUser');

			if ($hasSession) {
				$matcher->willReturn($this->createMock(IUser::class));
			} else {
				$this->expectException(\RuntimeException::class);
			}
		}

		$iterator = $factory->getLanguageIterator($iUserMock);
		$this->assertInstanceOf(ILanguageIterator::class, $iterator);
	}
}
