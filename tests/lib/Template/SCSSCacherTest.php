<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Template;

use OC\AppConfig;
use OC\Files\AppData\AppData;
use OC\Files\AppData\Factory;
use OC\Template\IconsCacher;
use OC\Template\SCSSCacher;
use OCA\Theming\ThemingDefaults;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IURLGenerator;

class SCSSCacherTest extends \Test\TestCase {
	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	protected $logger;
	/** @var IAppData|\PHPUnit\Framework\MockObject\MockObject */
	protected $appData;
	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	protected $urlGenerator;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;
	/** @var ThemingDefaults|\PHPUnit\Framework\MockObject\MockObject */
	protected $themingDefaults;
	/** @var SCSSCacher */
	protected $scssCacher;
	/** @var ICache|\PHPUnit\Framework\MockObject\MockObject */
	protected $depsCache;
	/** @var ICacheFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $isCachedCache;
	/** @var ICacheFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $cacheFactory;
	/** @var IconsCacher|\PHPUnit\Framework\MockObject\MockObject */
	protected $iconsCacher;
	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $timeFactory;
	/** @var AppConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $appConfig;

	protected function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(ILogger::class);
		$this->appData = $this->createMock(AppData::class);
		$this->iconsCacher = $this->createMock(IconsCacher::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		/** @var Factory|\PHPUnit\Framework\MockObject\MockObject $factory */
		$factory = $this->createMock(Factory::class);
		$factory->method('get')->with('css')->willReturn($this->appData);

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator->expects($this->any())
			->method('getBaseUrl')
			->willReturn('http://localhost/nextcloud');

		$this->config = $this->createMock(IConfig::class);
		$this->config->expects($this->any())
			->method('getAppValue')
			->will($this->returnCallback(function ($appId, $configKey, $defaultValue) {
				return $defaultValue;
			}));
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->depsCache = $this->createMock(ICache::class);
		$this->isCachedCache = $this->createMock(ICache::class);
		$this->cacheFactory
			->method('createDistributed')
			->withConsecutive()
			->willReturnOnConsecutiveCalls(
				$this->depsCache,
				$this->isCachedCache,
				$this->createMock(ICache::class)
			);

		$this->themingDefaults = $this->createMock(ThemingDefaults::class);
		$this->themingDefaults->expects($this->any())->method('getScssVariables')->willReturn([]);

		$iconsFile = $this->createMock(ISimpleFile::class);
		$this->iconsCacher->expects($this->any())
			->method('getCachedCSS')
			->willReturn($iconsFile);

		$this->appConfig = $this->createMock(AppConfig::class);

		$this->scssCacher = new SCSSCacher(
			$this->logger,
			$factory,
			$this->urlGenerator,
			$this->config,
			$this->themingDefaults,
			\OC::$SERVERROOT,
			$this->cacheFactory,
			$this->iconsCacher,
			$this->timeFactory,
			$this->appConfig
		);
	}

	public function testProcessUncachedFileNoAppDataFolder() {
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->any())->method('getSize')->willReturn(1);

		$this->appData->expects($this->once())->method('getFolder')->with('core')->willThrowException(new NotFoundException());
		$this->appData->expects($this->once())->method('newFolder')->with('core')->willReturn($folder);
		$this->appData->method('getDirectoryListing')->willReturn([]);

		$fileDeps = $this->createMock(ISimpleFile::class);
		$gzfile = $this->createMock(ISimpleFile::class);
		$filePrefix = substr(md5(\OC_Util::getVersionString('core')), 0, 4) . '-' .
					  substr(md5('http://localhost/nextcloud/index.php'), 0, 4) . '-';

		$folder->method('getFile')
			->willReturnCallback(function ($path) use ($file, $gzfile, $filePrefix) {
				if ($path === $filePrefix.'styles.css') {
					return $file;
				} elseif ($path === $filePrefix.'styles.css.deps') {
					throw new NotFoundException();
				} elseif ($path === $filePrefix.'styles.css.gzip') {
					return $gzfile;
				} else {
					$this->fail();
				}
			});
		$folder->expects($this->once())
			->method('newFile')
			->with($filePrefix.'styles.css.deps')
			->willReturn($fileDeps);

		$this->urlGenerator->expects($this->once())
			->method('getBaseUrl')
			->willReturn('http://localhost/nextcloud');

		$this->iconsCacher->expects($this->any())
			->method('setIconsCss')
			->willReturn('scss {}');

		$actual = $this->scssCacher->process(\OC::$SERVERROOT, '/core/css/styles.scss', 'core');
		$this->assertTrue($actual);
	}

	public function testProcessUncachedFile() {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->expects($this->once())->method('getFolder')->with('core')->willReturn($folder);
		$this->appData->method('getDirectoryListing')->willReturn([]);
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->any())->method('getSize')->willReturn(1);
		$fileDeps = $this->createMock(ISimpleFile::class);
		$gzfile = $this->createMock(ISimpleFile::class);
		$filePrefix = substr(md5(\OC_Util::getVersionString('core')), 0, 4) . '-' .
					  substr(md5('http://localhost/nextcloud/index.php'), 0, 4) . '-';

		$folder->method('getFile')
			->willReturnCallback(function ($path) use ($file, $gzfile, $filePrefix) {
				if ($path === $filePrefix.'styles.css') {
					return $file;
				} elseif ($path === $filePrefix.'styles.css.deps') {
					throw new NotFoundException();
				} elseif ($path === $filePrefix.'styles.css.gzip') {
					return $gzfile;
				} else {
					$this->fail();
				}
			});
		$folder->expects($this->once())
			->method('newFile')
			->with($filePrefix.'styles.css.deps')
			->willReturn($fileDeps);

		$this->iconsCacher->expects($this->any())
			->method('setIconsCss')
			->willReturn('scss {}');

		$actual = $this->scssCacher->process(\OC::$SERVERROOT, '/core/css/styles.scss', 'core');
		$this->assertTrue($actual);
	}

	public function testProcessCachedFile() {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->expects($this->once())->method('getFolder')->with('core')->willReturn($folder);
		$this->appData->method('getDirectoryListing')->willReturn([]);
		$file = $this->createMock(ISimpleFile::class);
		$fileDeps = $this->createMock(ISimpleFile::class);
		$fileDeps->expects($this->any())->method('getSize')->willReturn(1);
		$gzFile = $this->createMock(ISimpleFile::class);
		$filePrefix = substr(md5(\OC_Util::getVersionString('core')), 0, 4) . '-' .
					  substr(md5('http://localhost/nextcloud/index.php'), 0, 4) . '-';

		$folder->method('getFile')
			->willReturnCallback(function ($name) use ($file, $fileDeps, $gzFile, $filePrefix) {
				if ($name === $filePrefix.'styles.css') {
					return $file;
				} elseif ($name === $filePrefix.'styles.css.deps') {
					return $fileDeps;
				} elseif ($name === $filePrefix.'styles.css.gzip') {
					return $gzFile;
				}
				$this->fail();
			});

		$this->iconsCacher->expects($this->any())
			->method('setIconsCss')
			->willReturn('scss {}');

		$actual = $this->scssCacher->process(\OC::$SERVERROOT, '/core/css/styles.scss', 'core');
		$this->assertTrue($actual);
	}

	public function testProcessCachedFileMemcache() {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('core')
			->willReturn($folder);
		$folder->method('getName')
			->willReturn('core');
		$this->appData->method('getDirectoryListing')->willReturn([]);

		$file = $this->createMock(ISimpleFile::class);

		$fileDeps = $this->createMock(ISimpleFile::class);
		$fileDeps->expects($this->any())->method('getSize')->willReturn(1);

		$gzFile = $this->createMock(ISimpleFile::class);
		$filePrefix = substr(md5(\OC_Util::getVersionString('core')), 0, 4) . '-' .
					  substr(md5('http://localhost/nextcloud/index.php'), 0, 4) . '-';
		$folder->method('getFile')
			->willReturnCallback(function ($name) use ($file, $fileDeps, $gzFile, $filePrefix) {
				if ($name === $filePrefix.'styles.css') {
					return $file;
				} elseif ($name === $filePrefix.'styles.css.deps') {
					return $fileDeps;
				} elseif ($name === $filePrefix.'styles.css.gzip') {
					return $gzFile;
				}
				$this->fail();
			});

		$this->iconsCacher->expects($this->any())
			->method('setIconsCss')
			->willReturn('scss {}');

		$actual = $this->scssCacher->process(\OC::$SERVERROOT, '/core/css/styles.scss', 'core');
		$this->assertTrue($actual);
	}

	public function testIsCachedNoFile() {
		$fileNameCSS = "styles.css";
		$folder = $this->createMock(ISimpleFolder::class);

		$folder->expects($this->at(0))->method('getFile')->with($fileNameCSS)->willThrowException(new NotFoundException());
		$this->appData->expects($this->any())
			->method('getFolder')
			->willReturn($folder);
		$actual = self::invokePrivate($this->scssCacher, 'isCached', [$fileNameCSS, 'core']);
		$this->assertFalse($actual);
	}

	public function testIsCachedNoDepsFile() {
		$fileNameCSS = "styles.css";
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);

		$file->expects($this->once())->method('getSize')->willReturn(1);
		$folder->method('getFile')
			->willReturnCallback(function ($path) use ($file) {
				if ($path === 'styles.css') {
					return $file;
				} elseif ($path === 'styles.css.deps') {
					throw new NotFoundException();
				} else {
					$this->fail();
				}
			});

		$this->appData->expects($this->any())
			->method('getFolder')
			->willReturn($folder);
		$actual = self::invokePrivate($this->scssCacher, 'isCached', [$fileNameCSS, 'core']);
		$this->assertFalse($actual);
	}
	public function testCacheNoFile() {
		$fileNameCSS = "styles.css";
		$fileNameSCSS = "styles.scss";
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$depsFile = $this->createMock(ISimpleFile::class);
		$gzipFile = $this->createMock(ISimpleFile::class);

		$webDir = "core/css";
		$path = \OC::$SERVERROOT . '/core/css/';

		$folder->method('getFile')->willThrowException(new NotFoundException());
		$folder->method('newFile')->willReturnCallback(function ($fileName) use ($file, $depsFile, $gzipFile) {
			if ($fileName === 'styles.css') {
				return $file;
			} elseif ($fileName === 'styles.css.deps') {
				return $depsFile;
			} elseif ($fileName === 'styles.css.gzip') {
				return $gzipFile;
			}
			throw new \Exception();
		});

		$this->iconsCacher->expects($this->any())
			->method('setIconsCss')
			->willReturn('scss {}');

		$file->expects($this->once())->method('putContent');
		$depsFile->expects($this->once())->method('putContent');
		$gzipFile->expects($this->once())->method('putContent');

		$actual = self::invokePrivate($this->scssCacher, 'cache', [$path, $fileNameCSS, $fileNameSCSS, $folder, $webDir]);
		$this->assertTrue($actual);
	}

	public function testCache() {
		$fileNameCSS = "styles.css";
		$fileNameSCSS = "styles.scss";
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$depsFile = $this->createMock(ISimpleFile::class);
		$gzipFile = $this->createMock(ISimpleFile::class);

		$webDir = "core/css";
		$path = \OC::$SERVERROOT;

		$folder->method('getFile')->willReturnCallback(function ($fileName) use ($file, $depsFile, $gzipFile) {
			if ($fileName === 'styles.css') {
				return $file;
			} elseif ($fileName === 'styles.css.deps') {
				return $depsFile;
			} elseif ($fileName === 'styles.css.gzip') {
				return $gzipFile;
			}
			throw new \Exception();
		});

		$file->expects($this->once())->method('putContent');
		$depsFile->expects($this->once())->method('putContent');
		$gzipFile->expects($this->once())->method('putContent');

		$this->iconsCacher->expects($this->any())
			->method('setIconsCss')
			->willReturn('scss {}');

		$actual = self::invokePrivate($this->scssCacher, 'cache', [$path, $fileNameCSS, $fileNameSCSS, $folder, $webDir]);
		$this->assertTrue($actual);
	}

	public function testCacheSuccess() {
		$fileNameCSS = "styles-success.css";
		$fileNameSCSS = "../../tests/data/scss/styles-success.scss";
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$depsFile = $this->createMock(ISimpleFile::class);
		$gzipFile = $this->createMock(ISimpleFile::class);

		$webDir = "tests/data/scss";
		$path = \OC::$SERVERROOT . $webDir;

		$folder->method('getFile')->willReturnCallback(function ($fileName) use ($file, $depsFile, $gzipFile) {
			if ($fileName === 'styles-success.css') {
				return $file;
			} elseif ($fileName === 'styles-success.css.deps') {
				return $depsFile;
			} elseif ($fileName === 'styles-success.css.gzip') {
				return $gzipFile;
			}
			throw new \Exception();
		});

		$this->iconsCacher->expects($this->at(0))
			->method('setIconsCss')
			->willReturn('body{background-color:#0082c9}');

		$file->expects($this->at(0))->method('putContent')->with($this->callback(
			function ($content) {
				return 'body{background-color:#0082c9}' === $content;
			}));
		$depsFile->expects($this->at(0))->method('putContent')->with($this->callback(
			function ($content) {
				$deps = json_decode($content, true);
				return array_key_exists(\OC::$SERVERROOT . '/core/css/variables.scss', $deps)
					&& array_key_exists(\OC::$SERVERROOT . '/tests/data/scss/styles-success.scss', $deps);
			}));
		$gzipFile->expects($this->at(0))->method('putContent')->with($this->callback(
			function ($content) {
				return gzdecode($content) === 'body{background-color:#0082c9}';
			}
		));

		$actual = self::invokePrivate($this->scssCacher, 'cache', [$path, $fileNameCSS, $fileNameSCSS, $folder, $webDir]);
		$this->assertTrue($actual);
	}

	public function testCacheFailure() {
		$fileNameCSS = "styles-error.css";
		$fileNameSCSS = "../../tests/data/scss/styles-error.scss";
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$depsFile = $this->createMock(ISimpleFile::class);

		$webDir = "/tests/data/scss";
		$path = \OC::$SERVERROOT . $webDir;

		$folder->expects($this->at(0))->method('getFile')->with($fileNameCSS)->willReturn($file);
		$folder->expects($this->at(1))->method('getFile')->with($fileNameCSS . '.deps')->willReturn($depsFile);

		$actual = self::invokePrivate($this->scssCacher, 'cache', [$path, $fileNameCSS, $fileNameSCSS, $folder, $webDir]);
		$this->assertFalse($actual);
	}

	public function dataRebaseUrls() {
		return [
			['#id { background-image: url(\'../img/image.jpg\'); }','#id { background-image: url(\'/apps/files/css/../img/image.jpg\'); }'],
			['#id { background-image: url("../img/image.jpg"); }','#id { background-image: url(\'/apps/files/css/../img/image.jpg\'); }'],
			['#id { background-image: url(\'/img/image.jpg\'); }','#id { background-image: url(\'/img/image.jpg\'); }'],
			['#id { background-image: url("http://example.com/test.jpg"); }','#id { background-image: url("http://example.com/test.jpg"); }'],
		];
	}

	/**
	 * @dataProvider dataRebaseUrls
	 */
	public function testRebaseUrls($scss, $expected) {
		$webDir = '/apps/files/css';
		$actual = self::invokePrivate($this->scssCacher, 'rebaseUrls', [$scss, $webDir]);
		$this->assertEquals($expected, $actual);
	}

	public function dataGetCachedSCSS() {
		return [
			['core', 'core/css/styles.scss', '/css/core/styles.css', \OC_Util::getVersionString()],
			['files', 'apps/files/css/styles.scss', '/css/files/styles.css', \OC_App::getAppVersion('files')]
		];
	}

	/**
	 * @param $appName
	 * @param $fileName
	 * @param $result
	 * @dataProvider dataGetCachedSCSS
	 */
	public function testGetCachedSCSS($appName, $fileName, $result, $version) {
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('core.Css.getCss', [
				'fileName' => substr(md5($version), 0, 4) . '-' .
							  substr(md5('http://localhost/nextcloud/index.php'), 0, 4) . '-styles.css',
				'appName' => $appName,
				'v' => 0,
			])
			->willReturn(\OC::$WEBROOT . $result);
		$actual = $this->scssCacher->getCachedSCSS($appName, $fileName);
		$this->assertEquals(substr($result, 1), $actual);
	}

	private function randomString() {
		return sha1(uniqid(mt_rand(), true));
	}

	private function rrmdir($directory) {
		$files = array_diff(scandir($directory), ['.','..']);
		foreach ($files as $file) {
			if (is_dir($directory . '/' . $file)) {
				$this->rrmdir($directory . '/' . $file);
			} else {
				unlink($directory . '/' . $file);
			}
		}
		return rmdir($directory);
	}

	public function dataGetWebDir() {
		return [
			// Root installation
			['/http/core/css', 		'core', '', '/http', '/core/css'],
			['/http/apps/scss/css', 'scss', '', '/http', '/apps/scss/css'],
			['/srv/apps2/scss/css', 'scss', '', '/http', '/apps2/scss/css'],
			// Sub directory install
			['/http/nextcloud/core/css', 	  'core', 	'/nextcloud', '/http/nextcloud', '/nextcloud/core/css'],
			['/http/nextcloud/apps/scss/css', 'scss', 	'/nextcloud', '/http/nextcloud', '/nextcloud/apps/scss/css'],
			['/srv/apps2/scss/css', 		  'scss', 	'/nextcloud', '/http/nextcloud', '/apps2/scss/css']
		];
	}

	/**
	 * @param $path
	 * @param $appName
	 * @param $webRoot
	 * @param $serverRoot
	 * @dataProvider dataGetWebDir
	 */
	public function testgetWebDir($path, $appName, $webRoot, $serverRoot, $correctWebDir) {
		$tmpDir = sys_get_temp_dir().'/'.$this->randomString();
		// Adding fake apps folder and create fake app install
		\OC::$APPSROOTS[] = [
			'path' => $tmpDir.'/srv/apps2',
			'url' => '/apps2',
			'writable' => false
		];
		mkdir($tmpDir.$path, 0777, true);
		$actual = self::invokePrivate($this->scssCacher, 'getWebDir', [$tmpDir.$path, $appName, $tmpDir.$serverRoot, $webRoot]);
		$this->assertEquals($correctWebDir, $actual);
		array_pop(\OC::$APPSROOTS);
		$this->rrmdir($tmpDir.$path);
	}

	public function testResetCache() {
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())
			->method('delete');

		$folder = $this->createMock(ISimpleFolder::class);
		$folder->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([$file]);

		$this->depsCache->expects($this->once())
			->method('clear')
			->with('');
		$this->isCachedCache->expects($this->once())
			->method('clear')
			->with('');
		$this->appData->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([$folder]);

		$this->scssCacher->resetCache();
	}
}
