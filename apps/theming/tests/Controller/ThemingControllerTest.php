<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Kyle Fazzari <kyrofa@ubuntu.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author rakekniven <mark.ziegler@rakekniven.de>
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

namespace OCA\Theming\Tests\Controller;

use OC\L10N\L10N;
use OC\Template\SCSSCacher;
use OCA\Theming\Controller\ThemingController;
use OCA\Theming\ImageManager;
use OCA\Theming\ThemingDefaults;
use OCA\Theming\Util;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ITempManager;
use OCP\IURLGenerator;
use Test\TestCase;

class ThemingControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var ThemingDefaults|\PHPUnit_Framework_MockObject_MockObject */
	private $themingDefaults;
	/** @var Util */
	private $util;
	/** @var \OCP\AppFramework\Utility\ITimeFactory */
	private $timeFactory;
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l10n;
	/** @var ThemingController */
	private $themingController;
	/** @var ITempManager */
	private $tempManager;
	/** @var IAppManager|\PHPUnit_Framework_MockObject_MockObject */
	private $appManager;
	/** @var IAppData|\PHPUnit_Framework_MockObject_MockObject */
	private $appData;
	/** @var ImageManager|\PHPUnit_Framework_MockObject_MockObject */
	private $imageManager;
	/** @var SCSSCacher */
	private $scssCacher;
	/** @var IURLGenerator */
	private $urlGenerator;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->config = $this->createMock(IConfig::class);
		$this->themingDefaults = $this->createMock(ThemingDefaults::class);
		$this->l10n = $this->createMock(L10N::class);
		$this->appData = $this->createMock(IAppData::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->util = new Util($this->config, $this->appManager, $this->appData);
		$this->tempManager = \OC::$server->getTempManager();
		$this->scssCacher = $this->createMock(SCSSCacher::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->imageManager = $this->createMock(ImageManager::class);

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->expects($this->any())
			->method('getTime')
			->willReturn(123);

		$this->overwriteService(ITimeFactory::class, $this->timeFactory);

		$this->themingController = new ThemingController(
			'theming',
			$this->request,
			$this->config,
			$this->themingDefaults,
			$this->util,
			$this->l10n,
			$this->tempManager,
			$this->appData,
			$this->scssCacher,
			$this->urlGenerator,
			$this->appManager,
			$this->imageManager
		);

		parent::setUp();
	}

	public function dataUpdateStylesheetSuccess() {
		return [
			['name', str_repeat('a', 250), 'Saved'],
			['url', 'https://nextcloud.com/' . str_repeat('a', 478), 'Saved'],
			['slogan', str_repeat('a', 500), 'Saved'],
			['color', '#0082c9', 'Saved'],
			['color', '#0082C9', 'Saved'],
			['color', '#0082C9', 'Saved'],
			['imprintUrl', 'https://nextcloud.com/' . str_repeat('a', 478), 'Saved'],
			['privacyUrl', 'https://nextcloud.com/' . str_repeat('a', 478), 'Saved'],
		];
	}

	/**
	 * @dataProvider dataUpdateStylesheetSuccess
	 *
	 * @param string $setting
	 * @param string $value
	 * @param string $message
	 */
	public function testUpdateStylesheetSuccess($setting, $value, $message) {
		$this->themingDefaults
			->expects($this->once())
			->method('set')
			->with($setting, $value);
		$this->l10n
			->expects($this->once())
			->method('t')
			->willReturnCallback(function ($str) {
				return $str;
			});
		$this->scssCacher
			->expects($this->once())
			->method('getCachedSCSS')
			->with('core', '/core/css/css-variables.scss')
			->willReturn('/core/css/someHash-css-variables.scss');
		$this->urlGenerator
			->expects($this->once())
			->method('linkTo')
			->with('', '/core/css/someHash-css-variables.scss')
			->willReturn('/nextcloudWebroot/core/css/someHash-css-variables.scss');

		$expected = new DataResponse(
			[
				'data' =>
					[
						'message' => $message,
						'serverCssUrl' => '/nextcloudWebroot/core/css/someHash-css-variables.scss',
					],
				'status' => 'success',
			]
		);
		$this->assertEquals($expected, $this->themingController->updateStylesheet($setting, $value));
	}

	public function dataUpdateStylesheetError() {
		return [
			['name', str_repeat('a', 251), 'The given name is too long'],
			['url', 'http://example.com/' . str_repeat('a', 501), 'The given web address is too long'],
			['url', str_repeat('a', 501), 'The given web address is not a valid URL'],
			['url', 'javascript:alert(1)', 'The given web address is not a valid URL'],
			['slogan', str_repeat('a', 501), 'The given slogan is too long'],
			['color', '0082C9', 'The given color is invalid'],
			['color', '#0082Z9', 'The given color is invalid'],
			['color', 'Nextcloud', 'The given color is invalid'],
			['imprintUrl', '0082C9', 'The given legal notice address is not a valid URL'],
			['imprintUrl', '0082C9', 'The given legal notice address is not a valid URL'],
			['imprintUrl', 'javascript:foo', 'The given legal notice address is not a valid URL'],
			['privacyUrl', '#0082Z9', 'The given privacy policy address is not a valid URL'],
		];
	}

	/**
	 * @dataProvider dataUpdateStylesheetError
	 *
	 * @param string $setting
	 * @param string $value
	 * @param string $message
	 */
	public function testUpdateStylesheetError($setting, $value, $message) {
		$this->themingDefaults
			->expects($this->never())
			->method('set')
			->with($setting, $value);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($str) {
				return $str;
			});

		$expected = new DataResponse(
			[
				'data' =>
					[
						'message' => $message,
					],
				'status' => 'error',
			],
			Http::STATUS_BAD_REQUEST
		);
		$this->assertEquals($expected, $this->themingController->updateStylesheet($setting, $value));
	}

	public function testUpdateLogoNoData() {
		$this->request
			->expects($this->at(0))
			->method('getParam')
			->with('key')
			->willReturn('logo');
		$this->request
			->expects($this->at(1))
			->method('getUploadedFile')
			->with('image')
			->willReturn(null);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($str) {
				return $str;
			});

		$expected = new DataResponse(
			[
				'data' =>
					[
						'message' => 'No file uploaded',
					],
				'status' => 'failure',
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);

		$this->assertEquals($expected, $this->themingController->uploadImage());
	}

	/**
	 * Checks that trying to upload an SVG favicon without imagemagick
	 * results in an unsupported media type response.
	 *
	 * @test
	 * @return void
	 */
	public function testUploadSVGFaviconWithoutImagemagick() {
		$this->imageManager
			->method('shouldReplaceIcons')
			->willReturn(false);

		$this->request
			->expects($this->at(0))
			->method('getParam')
			->with('key')
			->willReturn('favicon');
		$this->request
			->expects($this->at(1))
			->method('getUploadedFile')
			->with('image')
			->willReturn([
				'tmp_name' => __DIR__  . '/../../../../tests/data/testimagelarge.svg',
				'type' => 'image/svg',
				'name' => 'testimagelarge.svg',
				'error' => 0,
			]);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($str) {
				return $str;
			});

		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('images')
			->willReturn($folder);

		$expected = new DataResponse(
			[
				'data' =>
					[
						'message' => 'Unsupported image type',
					],
				'status' => 'failure'
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);

		$this->assertEquals($expected, $this->themingController->uploadImage());
	}

	public function testUpdateLogoInvalidMimeType() {
		$this->request
			->expects($this->at(0))
			->method('getParam')
			->with('key')
			->willReturn('logo');
		$this->request
			->expects($this->at(1))
			->method('getUploadedFile')
			->with('image')
			->willReturn([
				'tmp_name' => __DIR__  . '/../../../../tests/data/lorem.txt',
				'type' => 'application/pdf',
				'name' => 'logo.pdf',
				'error' => 0,
			]);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($str) {
				return $str;
			});

		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('images')
			->willReturn($folder);

		$expected = new DataResponse(
			[
				'data' =>
					[
						'message' => 'Unsupported image type',
					],
				'status' => 'failure'
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);

		$this->assertEquals($expected, $this->themingController->uploadImage());
	}

	public function dataUpdateImages() {
		return [
			['image/jpeg', false],
			['image/jpeg', true],
			['image/gif'],
			['image/png'],
			['image/svg+xml'],
			['image/svg']
		];
	}

	/** @dataProvider dataUpdateImages */
	public function testUpdateLogoNormalLogoUpload($mimeType, $folderExists=true) {
		$tmpLogo = \OC::$server->getTempManager()->getTemporaryFolder() . '/logo.svg';
		$destination = \OC::$server->getTempManager()->getTemporaryFolder();

		touch($tmpLogo);
		copy(__DIR__ . '/../../../../tests/data/testimage.png', $tmpLogo);
		$this->request
			->expects($this->at(0))
			->method('getParam')
			->with('key')
			->willReturn('logo');
		$this->request
			->expects($this->at(1))
			->method('getUploadedFile')
			->with('image')
			->willReturn([
				'tmp_name' => $tmpLogo,
				'type' => $mimeType,
				'name' => 'logo.svg',
				'error' => 0,
			]);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($str) {
				return $str;
			});


		$file = $this->createMock(ISimpleFile::class);
		$folder = $this->createMock(ISimpleFolder::class);
		if($folderExists) {
			$this->appData
				->expects($this->once())
				->method('getFolder')
				->with('images')
				->willReturn($folder);
		} else {
			$this->appData
				->expects($this->at(0))
				->method('getFolder')
				->with('images')
				->willThrowException(new NotFoundException());
			$this->appData
				->expects($this->at(1))
				->method('newFolder')
				->with('images')
				->willReturn($folder);
		}
		$folder->expects($this->once())
			->method('newFile')
			->with('logo')
			->willReturn($file);
		$this->urlGenerator->expects($this->once())
			->method('linkTo')
			->willReturn('serverCss');
		$this->imageManager->expects($this->once())
			->method('getImageUrl')
			->with('logo')
			->willReturn('imageUrl');
		$expected = new DataResponse(
			[
				'data' =>
					[
						'name' => 'logo.svg',
						'message' => 'Saved',
						'url' => 'imageUrl',
						'serverCssUrl' => 'serverCss'
					],
				'status' => 'success'
			]
		);

		$this->assertEquals($expected, $this->themingController->uploadImage());
	}

	/** @dataProvider dataUpdateImages */
	public function testUpdateLogoLoginScreenUpload($folderExists) {
		$tmpLogo = \OC::$server->getTempManager()->getTemporaryFolder() . 'logo.png';

		touch($tmpLogo);
		copy(__DIR__ . '/../../../../tests/data/desktopapp.png', $tmpLogo);
		$this->request
			->expects($this->at(0))
			->method('getParam')
			->with('key')
			->willReturn('background');
		$this->request
			->expects($this->at(1))
			->method('getUploadedFile')
			->with('image')
			->willReturn([
				'tmp_name' => $tmpLogo,
				'type' => 'image/svg+xml',
				'name' => 'logo.svg',
				'error' => 0,
			]);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($str) {
				return $str;
			});

		$file = $this->createMock(ISimpleFile::class);
		$folder = $this->createMock(ISimpleFolder::class);
		if($folderExists) {
			$this->appData
				->expects($this->once())
				->method('getFolder')
				->with('images')
				->willReturn($folder);
		} else {
			$this->appData
				->expects($this->at(0))
				->method('getFolder')
				->with('images')
				->willThrowException(new NotFoundException());
			$this->appData
				->expects($this->at(1))
				->method('newFolder')
				->with('images')
				->willReturn($folder);
		}
		$folder->expects($this->once())
			->method('newFile')
			->with('background')
			->willReturn($file);

		$this->urlGenerator->expects($this->once())
			->method('linkTo')
			->willReturn('serverCss');
		$this->imageManager->expects($this->once())
			->method('getImageUrl')
			->with('background')
			->willReturn('imageUrl');
		$expected = new DataResponse(
			[
				'data' =>
					[
						'name' => 'logo.svg',
						'message' => 'Saved',
						'url' => 'imageUrl',
						'serverCssUrl' => 'serverCss'
					],
				'status' => 'success'
			]
		);
		$this->assertEquals($expected, $this->themingController->uploadImage());
	}

	public function testUpdateLogoLoginScreenUploadWithInvalidImage() {
		$tmpLogo = \OC::$server->getTempManager()->getTemporaryFolder() . '/logo.svg';

		touch($tmpLogo);
		file_put_contents($tmpLogo, file_get_contents(__DIR__  . '/../../../../tests/data/data.zip'));
		$this->request
			->expects($this->at(0))
			->method('getParam')
			->with('key')
			->willReturn('logo');
		$this->request
			->expects($this->at(1))
			->method('getUploadedFile')
			->with('image')
			->willReturn([
				'tmp_name' => $tmpLogo,
				'type' => 'foobar',
				'name' => 'logo.svg',
				'error' => 0,
			]);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($str) {
				return $str;
			});

		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData
			->expects($this->once())
			->method('getFolder')
			->with('images')
			->willReturn($folder);

		$expected = new DataResponse(
			[
				'data' =>
					[
						'message' => 'Unsupported image type',
					],
				'status' => 'failure'
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $this->themingController->uploadImage());
	}

	public function dataPhpUploadErrors() {
		return [
			[UPLOAD_ERR_INI_SIZE, 'The uploaded file exceeds the upload_max_filesize directive in php.ini'],
			[UPLOAD_ERR_FORM_SIZE, 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'],
			[UPLOAD_ERR_PARTIAL, 'The file was only partially uploaded'],
			[UPLOAD_ERR_NO_FILE, 'No file was uploaded'],
			[UPLOAD_ERR_NO_TMP_DIR, 'Missing a temporary folder'],
			[UPLOAD_ERR_CANT_WRITE, 'Could not write file to disk'],
			[UPLOAD_ERR_EXTENSION, 'A PHP extension stopped the file upload'],
		];
	}

	/**
	 * @dataProvider dataPhpUploadErrors
	 */
	public function testUpdateLogoLoginScreenUploadWithInvalidImageUpload($error, $expectedErrorMessage) {
		$this->request
			->expects($this->at(0))
			->method('getParam')
			->with('key')
			->willReturn('background');
		$this->request
			->expects($this->at(1))
			->method('getUploadedFile')
			->with('image')
			->willReturn([
				'tmp_name' => '',
				'type' => 'image/svg+xml',
				'name' => 'logo.svg',
				'error' => $error,
			]);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($str) {
				return $str;
			});

		$expected = new DataResponse(
			[
				'data' =>
					[
						'message' => $expectedErrorMessage,
					],
				'status' => 'failure'
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $this->themingController->uploadImage());
	}

	/**
	 * @dataProvider dataPhpUploadErrors
	 */
	public function testUpdateLogoUploadWithInvalidImageUpload($error, $expectedErrorMessage) {
		$this->request
			->expects($this->at(0))
			->method('getParam')
			->with('key')
			->willReturn('background');
		$this->request
			->expects($this->at(1))
			->method('getUploadedFile')
			->with('image')
			->willReturn([
				'tmp_name' => '',
				'type' => 'text/svg',
				'name' => 'logo.svg',
				'error' => $error,
			]);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($str) {
				return $str;
			});

		$expected = new DataResponse(
			[
				'data' =>
					[
						'message' => $expectedErrorMessage
					],
				'status' => 'failure'
			],
			Http::STATUS_UNPROCESSABLE_ENTITY
		);
		$this->assertEquals($expected, $this->themingController->uploadImage());
	}

	public function testUndo() {
		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Saved')
			->willReturn('Saved');
		$this->themingDefaults
			->expects($this->once())
			->method('undo')
			->with('MySetting')
			->willReturn('MyValue');
		$this->scssCacher
			->expects($this->once())
			->method('getCachedSCSS')
			->with('core', '/core/css/css-variables.scss')
			->willReturn('/core/css/someHash-css-variables.scss');
		$this->urlGenerator
			->expects($this->once())
			->method('linkTo')
			->with('', '/core/css/someHash-css-variables.scss')
			->willReturn('/nextcloudWebroot/core/css/someHash-css-variables.scss');

		$expected = new DataResponse(
			[
				'data' =>
					[
						'value' => 'MyValue',
						'message' => 'Saved',
						'serverCssUrl' => '/nextcloudWebroot/core/css/someHash-css-variables.scss',
					],
				'status' => 'success'
			]
		);
		$this->assertEquals($expected, $this->themingController->undo('MySetting'));
	}

	public function dataUndoDelete() {
		return [
			[ 'backgroundMime', 'background' ],
			[ 'logoMime', 'logo' ]
		];
	}

	/** @dataProvider dataUndoDelete */
	public function testUndoDelete($value, $filename) {
		$this->l10n
			->expects($this->once())
			->method('t')
			->with('Saved')
			->willReturn('Saved');
		$this->themingDefaults
			->expects($this->once())
			->method('undo')
			->with($value)
			->willReturn($value);
		$this->scssCacher
			->expects($this->once())
			->method('getCachedSCSS')
			->with('core', '/core/css/css-variables.scss')
			->willReturn('/core/css/someHash-css-variables.scss');
		$this->urlGenerator
			->expects($this->once())
			->method('linkTo')
			->with('', '/core/css/someHash-css-variables.scss')
			->willReturn('/nextcloudWebroot/core/css/someHash-css-variables.scss');
		$this->imageManager->expects($this->once())
			->method('delete')
			->with($filename);

		$expected = new DataResponse(
			[
				'data' =>
					[
						'value' => $value,
						'message' => 'Saved',
						'serverCssUrl' => '/nextcloudWebroot/core/css/someHash-css-variables.scss',
					],
				'status' => 'success'
			]
		);
		$this->assertEquals($expected, $this->themingController->undo($value));
	}



	public function testGetLogoNotExistent() {
		$this->imageManager->method('getImage')
			->with($this->equalTo('logo'))
			->willThrowException(new NotFoundException());

		$expected = new Http\NotFoundResponse();
		$this->assertEquals($expected, $this->themingController->getImage('logo'));
	}

	public function testGetLogo() {
		$file = $this->createMock(ISimpleFile::class);
		$this->imageManager->expects($this->once())
			->method('getImage')
			->willReturn($file);
		$this->config
			->expects($this->any())
			->method('getAppValue')
			->with('theming', 'logoMime', '')
			->willReturn('text/svg');

		@$expected = new Http\FileDisplayResponse($file);
		$expected->cacheFor(3600);
		$expected->addHeader('Content-Type', 'text/svg');
		$expected->addHeader('Content-Disposition', 'attachment; filename="logo"');
		$csp = new Http\ContentSecurityPolicy();
		$csp->allowInlineStyle();
		$expected->setContentSecurityPolicy($csp);
		@$this->assertEquals($expected, $this->themingController->getImage('logo'));
	}


	public function testGetLoginBackgroundNotExistent() {
		$this->imageManager->method('getImage')
			->with($this->equalTo('background'))
			->willThrowException(new NotFoundException());
		$expected = new Http\NotFoundResponse();
		$this->assertEquals($expected, $this->themingController->getImage('background'));
	}

	public function testGetLoginBackground() {
		$file = $this->createMock(ISimpleFile::class);
		$this->imageManager->expects($this->once())
			->method('getImage')
			->willReturn($file);

		$this->config
			->expects($this->any())
			->method('getAppValue')
			->with('theming', 'backgroundMime', '')
			->willReturn('image/png');

		@$expected = new Http\FileDisplayResponse($file);
		$expected->cacheFor(3600);
		$expected->addHeader('Content-Type', 'image/png');
		$expected->addHeader('Content-Disposition', 'attachment; filename="background"');
		$csp = new Http\ContentSecurityPolicy();
		$csp->allowInlineStyle();
		$expected->setContentSecurityPolicy($csp);
		@$this->assertEquals($expected, $this->themingController->getImage('background'));
	}


	public function testGetStylesheet() {
		$this->appManager->expects($this->once())->method('getAppPath')->with('theming')->willReturn(\OC::$SERVERROOT . '/theming');
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->any())->method('getName')->willReturn('theming.css');
		$file->expects($this->any())->method('getContent')->willReturn('compiled');
		$this->scssCacher->expects($this->once())->method('process')->willReturn(true);
		$this->scssCacher->expects($this->once())->method('getCachedCSS')->willReturn($file);

		$response = new Http\FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => 'text/css']);
		$response->cacheFor(86400);

		$actual = $this->themingController->getStylesheet();
		$this->assertEquals($response, $actual);
	}

	public function testGetStylesheetFails() {
		$this->appManager->expects($this->once())->method('getAppPath')->with('theming')->willReturn(\OC::$SERVERROOT . '/theming');
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->any())->method('getName')->willReturn('theming.css');
		$file->expects($this->any())->method('getContent')->willReturn('compiled');
		$this->scssCacher->expects($this->once())->method('process')->willReturn(true);
		$this->scssCacher->expects($this->once())->method('getCachedCSS')->willThrowException(new NotFoundException());
		$response = new Http\NotFoundResponse();

		$actual = $this->themingController->getStylesheet();
		$this->assertEquals($response, $actual);
	}

	public function testGetStylesheetOutsideServerroot() {
		$this->appManager->expects($this->once())->method('getAppPath')->with('theming')->willReturn('/outside/serverroot/theming');
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->any())->method('getName')->willReturn('theming.css');
		$file->expects($this->any())->method('getContent')->willReturn('compiled');
		$this->scssCacher->expects($this->once())->method('process')->with('/outside/serverroot/theming', 'css/theming.scss', 'theming')->willReturn(true);
		$this->scssCacher->expects($this->once())->method('getCachedCSS')->willReturn($file);

		$response = new Http\FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => 'text/css']);
		$response->cacheFor(86400);

		$actual = $this->themingController->getStylesheet();
		$this->assertEquals($response, $actual);
	}

	public function testGetJavascript() {
		$this->themingDefaults
			->expects($this->at(0))
			->method('getName')
			->willReturn("");
		$this->themingDefaults
			->expects($this->at(1))
			->method('getBaseUrl')
			->willReturn("");
		$this->themingDefaults
			->expects($this->at(2))
			->method('getSlogan')
			->willReturn("");
		$this->themingDefaults
			->expects($this->at(3))
			->method('getColorPrimary')
			->willReturn("#000");


		$expectedResponse = '(function() {
	OCA.Theming = {
		name: "",
		url: "",
		slogan: "",
		color: "#000",
		imprintUrl: null,
		privacyUrl: null,
		inverted: false,
		cacheBuster: null
	};
})();';
		$expected = new Http\DataDownloadResponse($expectedResponse, 'javascript', 'text/javascript');
		$expected->cacheFor(3600);
		@$this->assertEquals($expected, $this->themingController->getJavascript());
	}
	public function testGetJavascriptInverted() {
		$this->themingDefaults
			->expects($this->at(0))
			->method('getName')
			->willReturn("Nextcloud");
		$this->themingDefaults
			->expects($this->at(1))
			->method('getBaseUrl')
			->willReturn("nextcloudurl");
		$this->themingDefaults
			->expects($this->at(2))
			->method('getSlogan')
			->willReturn("awesome");
		$this->themingDefaults
			->expects($this->any())
			->method('getColorPrimary')
			->willReturn("#ffffff");

		$expectedResponse = '(function() {
	OCA.Theming = {
		name: "Nextcloud",
		url: "nextcloudurl",
		slogan: "awesome",
		color: "#ffffff",
		imprintUrl: null,
		privacyUrl: null,
		inverted: true,
		cacheBuster: null
	};
})();';
		$expected = new Http\DataDownloadResponse($expectedResponse, 'javascript', 'text/javascript');
		$expected->cacheFor(3600);
		@$this->assertEquals($expected, $this->themingController->getJavascript());
	}

	public function testGetManifest() {
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('theming', 'cachebuster', '0')
			->willReturn('0');
		$this->themingDefaults
			->expects($this->any())
			->method('getName')
			->willReturn('Nextcloud');
		$this->urlGenerator
			->expects($this->at(0))
			->method('getBaseUrl')
			->willReturn('localhost');
		$this->urlGenerator
			->expects($this->at(1))
			->method('linkToRoute')
			->with('theming.Icon.getTouchIcon', ['app' => 'core'])
			->willReturn('touchicon');
		$this->urlGenerator
			->expects($this->at(2))
			->method('linkToRoute')
			->with('theming.Icon.getFavicon', ['app' => 'core'])
			->willReturn('favicon');
		$response = new Http\JSONResponse([
			'name' => 'Nextcloud',
			'start_url' => 'localhost',
			'icons' =>
				[
					[
						'src' => 'touchicon?v=0',
						'type'=> 'image/png',
						'sizes'=> '128x128'
					],
					[
						'src' => 'favicon?v=0',
						'type' => 'image/svg+xml',
						'sizes' => '16x16'
					]
				],
			'display' => 'standalone'
		]);
		$response->cacheFor(3600);
		$this->assertEquals($response, $this->themingController->getManifest('core'));
	}

}
