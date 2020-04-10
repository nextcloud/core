<?php
/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Template;

use OC\SystemConfig;
use OC\Template\JSCombiner;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\ILogger;
use OCP\IURLGenerator;

class JSCombinerTest extends \Test\TestCase {
	/** @var IAppData|\PHPUnit_Framework_MockObject_MockObject */
	protected $appData;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $urlGenerator;
	/** @var SystemConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;
	/** @var ICache|\PHPUnit_Framework_MockObject_MockObject */
	protected $depsCache;
	/** @var JSCombiner */
	protected $jsCombiner;
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	protected $logger;
	/** @var ICacheFactory|\PHPUnit_Framework_MockObject_MockObject */
	protected $cacheFactory;

	protected function setUp(): void {
		parent::setUp();

		$this->appData = $this->createMock(IAppData::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(SystemConfig::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->depsCache = $this->createMock(ICache::class);
		$this->cacheFactory->expects($this->at(0))
			->method('createDistributed')
			->willReturn($this->depsCache);
		$this->logger = $this->createMock(ILogger::class);
		$this->jsCombiner = new JSCombiner(
			$this->appData,
			$this->urlGenerator,
			$this->cacheFactory,
			$this->config,
			$this->logger
		);

	}

	public function testProcessDebugMode() {
		$this->config
			->expects($this->once())
			->method('getValue')
			->with('debug')
			->willReturn(true);

		$actual = $this->jsCombiner->process(__DIR__, '/data/combine.json', 'awesomeapp');
		$this->assertFalse($actual);
	}

	public function testProcessNotInstalled() {
		$this->config
			->expects($this->at(0))
			->method('getValue')
			->with('debug')
			->willReturn(false);
		$this->config
			->expects($this->at(1))
			->method('getValue')
			->with('installed')
			->willReturn(false);

		$actual = $this->jsCombiner->process(__DIR__, '/data/combine.json', 'awesomeapp');
		$this->assertFalse($actual);
	}

	public function testProcessUncachedFileNoAppDataFolder() {
		$this->config
			->expects($this->at(0))
			->method('getValue')
			->with('debug')
			->willReturn(false);
		$this->config
			->expects($this->at(1))
			->method('getValue')
			->with('installed')
			->willReturn(true);
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->expects($this->once())->method('getFolder')->with('awesomeapp')->willThrowException(new NotFoundException());
		$this->appData->expects($this->once())->method('newFolder')->with('awesomeapp')->willReturn($folder);
		$file = $this->createMock(ISimpleFile::class);
		$gzfile = $this->createMock(ISimpleFile::class);

		$fileDeps = $this->createMock(ISimpleFile::class);

		$folder->method('getFile')
			->willReturnCallback(function ($path) use ($file, $gzfile) {
				if ($path === 'combine.js') {
					return $file;
				} else if ($path === 'combine.js.deps') {
					throw new NotFoundException();
				} else if ($path === 'combine.js.gzip') {
					return $gzfile;
				}
				$this->fail();
			});
		$folder->expects($this->once())
			->method('newFile')
			->with('combine.js.deps')
			->willReturn($fileDeps);

		$actual = $this->jsCombiner->process(__DIR__, '/data/combine.json', 'awesomeapp');
		$this->assertTrue($actual);
	}

	public function testProcessUncachedFile() {
		$this->config
			->expects($this->at(0))
			->method('getValue')
			->with('debug')
			->willReturn(false);
		$this->config
			->expects($this->at(1))
			->method('getValue')
			->with('installed')
			->willReturn(true);
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->expects($this->once())->method('getFolder')->with('awesomeapp')->willReturn($folder);
		$file = $this->createMock(ISimpleFile::class);
		$fileDeps = $this->createMock(ISimpleFile::class);
		$gzfile = $this->createMock(ISimpleFile::class);

		$folder->method('getFile')
			->willReturnCallback(function ($path) use ($file, $gzfile) {
				if ($path === 'combine.js') {
					return $file;
				} else if ($path === 'combine.js.deps') {
					throw new NotFoundException();
				} else if ($path === 'combine.js.gzip') {
					return $gzfile;
				}
				$this->fail();
			});
		$folder->expects($this->once())
			->method('newFile')
			->with('combine.js.deps')
			->willReturn($fileDeps);

		$actual = $this->jsCombiner->process(__DIR__, '/data/combine.json', 'awesomeapp');
		$this->assertTrue($actual);
	}

	public function testProcessCachedFile() {
		$this->config
			->expects($this->at(0))
			->method('getValue')
			->with('debug')
			->willReturn(false);
		$this->config
			->expects($this->at(1))
			->method('getValue')
			->with('installed')
			->willReturn(true);
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->expects($this->once())->method('getFolder')->with('awesomeapp')->willReturn($folder);
		$file = $this->createMock(ISimpleFile::class);

		$fileDeps = $this->createMock(ISimpleFile::class);

		$fileDeps->expects($this->once())->method('getContent')->willReturn('{}');

		$folder->method('fileExists')
			->with('combine.js')
			->willReturn(true);

		$folder->method('getFile')
			->willReturnCallback(function ($path) use ($file, $fileDeps) {
				if ($path === 'combine.js') {
					return $file;
				}

				if ($path === 'combine.js.deps') {
					return $fileDeps;
				}

				$this->fail();
			});

		$actual = $this->jsCombiner->process(__DIR__, '/data/combine.json', 'awesomeapp');
		$this->assertTrue($actual);
	}

	public function testProcessCachedFileMemcache() {
		$this->config
			->expects($this->at(0))
			->method('getValue')
			->with('debug')
			->willReturn(false);
		$this->config
			->expects($this->at(1))
			->method('getValue')
			->with('installed')
			->willReturn(true);
		$folder = $this->createMock(ISimpleFolder::class);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('awesomeapp')
			->willReturn($folder);
		$folder->method('getName')
			->willReturn('awesomeapp');
		$folder->method('fileExists')
			->with('combine.js')
			->willReturn(true);

		$file = $this->createMock(ISimpleFile::class);

		$this->depsCache->method('get')
			->with('awesomeapp-combine.js.deps')
			->willReturn('{}');

		$folder->method('getFile')
			->willReturnCallback(function ($path) use ($file) {
				if ($path === 'combine.js') {
					return $file;
				}
				$this->fail();
			});

		$actual = $this->jsCombiner->process(__DIR__, '/data/combine.json', 'awesomeapp');
		$this->assertTrue($actual);
	}

	public function testIsCachedNoDepsFile() {
		$fileName = 'combine.json';
		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);

		$folder->method('getFile')
			->willReturnCallback(function ($path) use ($file) {
				if ($path === 'combine.js') {
					return $file;
				}
				if ($path === 'combine.js.deps') {
					throw new NotFoundException();
				}
				$this->fail();
			});

		$actual = self::invokePrivate($this->jsCombiner, 'isCached', [$fileName, $folder]);
		$this->assertFalse($actual);
	}

	public function testIsCachedWithNotExistingFile() {
		$fileName = 'combine.json';
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('fileExists')
			->with('combine.js')
			->willReturn(true);
		$file = $this->createMock(ISimpleFile::class);
		$folder->method('getFile')
			->with('combine.js.deps')
			->willReturn($file);
		$file->expects($this->once())
			->method('getContent')
			->willReturn(json_encode(['/etc/certainlynotexisting/file/ihope' => 10000]));

		$actual = self::invokePrivate($this->jsCombiner, 'isCached', [$fileName, $folder]);
		$this->assertFalse($actual);
	}

	public function testIsCachedWithOlderMtime() {
		$fileName = 'combine.json';
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('fileExists')
			->with('combine.js')
			->willReturn(true);
		$file = $this->createMock(ISimpleFile::class);
		$folder->method('getFile')
			->with('combine.js.deps')
			->willReturn($file);
		$file->expects($this->once())
			->method('getContent')
			->willReturn(json_encode([__FILE__ => 1234]));

		$actual = self::invokePrivate($this->jsCombiner, 'isCached', [$fileName, $folder]);
		$this->assertFalse($actual);
	}

	public function testIsCachedWithoutContent() {
		$fileName = 'combine.json';
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('fileExists')
			->with('combine.js')
			->willReturn(true);
		$file = $this->createMock(ISimpleFile::class);
		$folder->method('getFile')
			->with('combine.js.deps')
			->willReturn($file);
		$file->expects($this->once())
			->method('getContent')
			->willReturn('');
		$this->logger->expects($this->once())
			->method('info')
			->with('JSCombiner: deps file empty: combine.js.deps');
		$actual = self::invokePrivate($this->jsCombiner, 'isCached', [$fileName, $folder]);
		$this->assertFalse($actual);
	}

	public function testCacheNoFile() {
		$fileName = 'combine.js';

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$depsFile = $this->createMock(ISimpleFile::class);
		$gzFile = $this->createMock(ISimpleFile::class);

		$path = __DIR__ . '/data/';

		$folder->method('getFile')->willThrowException(new NotFoundException());

		$folder->method('newFile')->willReturnCallback(
			function ($filename) use ($file, $depsFile, $gzFile) {
				if ($filename === 'combine.js') {
					return $file;
				} else if ($filename === 'combine.js.deps') {
					return $depsFile;
				} else if ($filename === 'combine.js.gzip') {
					return $gzFile;
				}
				$this->fail();
			}
		);

		$file->expects($this->once())->method('putContent');
		$depsFile->expects($this->once())->method('putContent');
		$gzFile->expects($this->once())->method('putContent');

		$actual = self::invokePrivate($this->jsCombiner, 'cache', [$path, 'combine.json', $folder]);
		$this->assertTrue($actual);
	}

	public function testCache() {
		$fileName = 'combine.js';

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$depsFile = $this->createMock(ISimpleFile::class);
		$gzFile = $this->createMock(ISimpleFile::class);

		$path = __DIR__ . '/data/';

		$folder->method('getFile')->willReturnCallback(
			function ($filename) use ($file, $depsFile, $gzFile) {
				if ($filename === 'combine.js') {
					return $file;
				} else if ($filename === 'combine.js.deps') {
					return $depsFile;
				} else if ($filename === 'combine.js.gzip') {
					return $gzFile;
				}
				$this->fail();
			}
		);

		$file->expects($this->once())->method('putContent');
		$depsFile->expects($this->once())->method('putContent');
		$gzFile->expects($this->once())->method('putContent');

		$actual = self::invokePrivate($this->jsCombiner, 'cache', [$path, 'combine.json', $folder]);
		$this->assertTrue($actual);
	}

	public function testCacheNotPermittedException() {
		$fileName = 'combine.js';

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$depsFile = $this->createMock(ISimpleFile::class);

		$path = __DIR__ . '/data/';

		$folder->expects($this->at(0))->method('getFile')->with($fileName)->willReturn($file);
		$folder->expects($this->at(1))->method('getFile')->with($fileName . '.deps')->willReturn($depsFile);

		$file->expects($this->at(0))
			->method('putContent')
			->with('var a = \'hello\';


var b = \'world\';


');
		$depsFile
			->expects($this->at(0))
			->method('putContent')
			->with($this->callback(
			function ($content) {
				$deps = json_decode($content, true);
				return array_key_exists(__DIR__ . '/data//1.js', $deps)
					&& array_key_exists(__DIR__ . '/data//2.js', $deps);
			}))
			->willThrowException(new NotPermittedException());

		$actual = self::invokePrivate($this->jsCombiner, 'cache', [$path, 'combine.json', $folder]);
		$this->assertFalse($actual);
	}

	public function testCacheSuccess() {
		$fileName = 'combine.js';

		$folder = $this->createMock(ISimpleFolder::class);
		$file = $this->createMock(ISimpleFile::class);
		$depsFile = $this->createMock(ISimpleFile::class);
		$gzFile = $this->createMock(ISimpleFile::class);

		$path = __DIR__ . '/data/';


		$folder->method('getFile')->willReturnCallback(
			function ($filename) use ($file, $depsFile, $gzFile) {
				if ($filename === 'combine.js') {
					return $file;
				} else if ($filename === 'combine.js.deps') {
					return $depsFile;
				} else if ($filename === 'combine.js.gzip') {
					return $gzFile;
				}
				$this->fail();
			}
		);

		$file->expects($this->at(0))
			->method('putContent')
			->with('var a = \'hello\';


var b = \'world\';


');
		$depsFile->expects($this->at(0))->method('putContent')->with($this->callback(
			function ($content) {
				$deps = json_decode($content, true);
				return array_key_exists(__DIR__ . '/data//1.js', $deps)
					&& array_key_exists(__DIR__ . '/data//2.js', $deps);
			}));
		$gzFile->expects($this->at(0))->method('putContent')->with($this->callback(
			function ($content) {
				return gzdecode($content) === 'var a = \'hello\';


var b = \'world\';


';
			}
		));

		$actual = self::invokePrivate($this->jsCombiner, 'cache', [$path, 'combine.json', $folder]);
		$this->assertTrue($actual);
	}

	public function dataGetCachedSCSS() {
		return [
			['awesomeapp', 'core/js/foo.json', '/js/core/foo.js'],
			['files', 'apps/files/js/foo.json', '/js/files/foo.js']
		];
	}

	/**
	 * @param $appName
	 * @param $fileName
	 * @param $result
	 * @dataProvider dataGetCachedSCSS
	 */
	public function testGetCachedSCSS($appName, $fileName, $result) {
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('core.Js.getJs', [
				'fileName' => 'foo.js',
				'appName' => $appName
			])
			->willReturn(\OC::$WEBROOT . $result);

		$actual = $this->jsCombiner->getCachedJS($appName, $fileName);
		$this->assertEquals(substr($result, 1), $actual);
	}

	public function testGetContent() {
		// Create temporary file with some content
		$tmpFile = \OC::$server->getTempManager()->getTemporaryFile('JSCombinerTest');
		$pathInfo = pathinfo($tmpFile);
		file_put_contents($tmpFile, json_encode(['/foo/bar/test', $pathInfo['dirname'] . '/js/mytest.js']));
		$tmpFilePathArray = explode('/', $pathInfo['basename']);
		array_pop($tmpFilePathArray);

		$expected = [
			'//foo/bar/test',
			'/' . implode('/', $tmpFilePathArray) . $pathInfo['dirname'] . '/js/mytest.js',
		];
		$this->assertEquals($expected, $this->jsCombiner->getContent($pathInfo['dirname'], $pathInfo['basename']));
	}

	public function testGetContentInvalidJson() {
		// Create temporary file with some content
		$tmpFile = \OC::$server->getTempManager()->getTemporaryFile('JSCombinerTest');
		$pathInfo = pathinfo($tmpFile);
		file_put_contents($tmpFile, 'CertainlyNotJson');
		$expected = [];
		$this->assertEquals($expected, $this->jsCombiner->getContent($pathInfo['dirname'], $pathInfo['basename']));
	}

	public function testResetCache() {
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())
			->method('delete');

		$folder = $this->createMock(ISimpleFolder::class);
		$folder->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([$file]);

		$cache = $this->createMock(ICache::class);
		$this->cacheFactory->expects($this->once())
			->method('createDistributed')
			->willReturn($cache);
		$cache->expects($this->once())
			->method('clear')
			->with('');
		$this->appData->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([$folder]);

		$this->jsCombiner->resetCache();
	}

}
