<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Cache;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use OC\Files\Cache\Cache;
use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchQuery;
use OCP\Files\Search\ISearchComparison;
use OCP\IUser;

class LongId extends \OC\Files\Storage\Temporary {
	public function getId() {
		return 'long:' . str_repeat('foo', 50) . parent::getId();
	}
}

/**
 * Class CacheTest
 *
 * @group DB
 *
 * @package Test\Files\Cache
 */
class CacheTest extends \Test\TestCase {
	/**
	 * @var \OC\Files\Storage\Temporary $storage ;
	 */
	protected $storage;
	/**
	 * @var \OC\Files\Storage\Temporary $storage2 ;
	 */
	protected $storage2;

	/**
	 * @var \OC\Files\Cache\Cache $cache
	 */
	protected $cache;
	/**
	 * @var \OC\Files\Cache\Cache $cache2
	 */
	protected $cache2;

	public function testGetNumericId() {
		$this->assertNotNull($this->cache->getNumericStorageId());
	}

	public function testSimple() {
		$file1 = 'foo';
		$file2 = 'foo/bar';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'foo/folder'];
		$data2 = ['size' => 1000, 'mtime' => 20, 'mimetype' => 'foo/file'];

		$this->assertFalse($this->cache->inCache($file1));
		$this->assertEquals($this->cache->get($file1), null);

		$id1 = $this->cache->put($file1, $data1);
		$this->assertTrue($this->cache->inCache($file1));
		$cacheData1 = $this->cache->get($file1);
		foreach ($data1 as $key => $value) {
			$this->assertEquals($value, $cacheData1[$key]);
		}
		$this->assertEquals($cacheData1['mimepart'], 'foo');
		$this->assertEquals($cacheData1['fileid'], $id1);
		$this->assertEquals($id1, $this->cache->getId($file1));

		$this->assertFalse($this->cache->inCache($file2));
		$id2 = $this->cache->put($file2, $data2);
		$this->assertTrue($this->cache->inCache($file2));
		$cacheData2 = $this->cache->get($file2);
		foreach ($data2 as $key => $value) {
			$this->assertEquals($value, $cacheData2[$key]);
		}
		$this->assertEquals($cacheData1['fileid'], $cacheData2['parent']);
		$this->assertEquals($cacheData2['fileid'], $id2);
		$this->assertEquals($id2, $this->cache->getId($file2));
		$this->assertEquals($id1, $this->cache->getParentId($file2));

		$newSize = 1050;
		$newId2 = $this->cache->put($file2, ['size' => $newSize]);
		$cacheData2 = $this->cache->get($file2);
		$this->assertEquals($newId2, $id2);
		$this->assertEquals($cacheData2['size'], $newSize);
		$this->assertEquals($cacheData1, $this->cache->get($file1));

		$this->cache->remove($file2);
		$this->assertFalse($this->cache->inCache($file2));
		$this->assertEquals($this->cache->get($file2), null);
		$this->assertTrue($this->cache->inCache($file1));

		$this->assertEquals($cacheData1, $this->cache->get($id1));
	}

	public function testPartial() {
		$file1 = 'foo';

		$this->cache->put($file1, ['size' => 10]);
		$this->assertEquals(['size' => 10], $this->cache->get($file1));

		$this->cache->put($file1, ['mtime' => 15]);
		$this->assertEquals(['size' => 10, 'mtime' => 15], $this->cache->get($file1));

		$this->cache->put($file1, ['size' => 12]);
		$this->assertEquals(['size' => 12, 'mtime' => 15], $this->cache->get($file1));
	}

	/**
	 * @dataProvider folderDataProvider
	 */
	public function testFolder($folder) {
		if (strpos($folder, 'F09F9890')) {
			// 4 byte UTF doesn't work on mysql
			$params = \OC::$server->get(\OC\DB\Connection::class)->getParams();
			if (\OC::$server->getDatabaseConnection()->getDatabasePlatform() instanceof MySqlPlatform && $params['charset'] !== 'utf8mb4') {
				$this->markTestSkipped('MySQL doesn\'t support 4 byte UTF-8');
			}
		}
		$file2 = $folder . '/bar';
		$file3 = $folder . '/foo';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];
		$fileData = [];
		$fileData['bar'] = ['size' => 1000, 'mtime' => 20, 'mimetype' => 'foo/file'];
		$fileData['foo'] = ['size' => 20, 'mtime' => 25, 'mimetype' => 'foo/file'];

		$this->cache->put($folder, $data1);
		$this->cache->put($file2, $fileData['bar']);
		$this->cache->put($file3, $fileData['foo']);

		$content = $this->cache->getFolderContents($folder);
		$this->assertEquals(count($content), 2);
		foreach ($content as $cachedData) {
			$data = $fileData[$cachedData['name']];
			foreach ($data as $name => $value) {
				$this->assertEquals($value, $cachedData[$name]);
			}
		}

		$file4 = $folder . '/unkownSize';
		$fileData['unkownSize'] = ['size' => -1, 'mtime' => 25, 'mimetype' => 'foo/file'];
		$this->cache->put($file4, $fileData['unkownSize']);

		$this->assertEquals(-1, $this->cache->calculateFolderSize($folder));

		$fileData['unkownSize'] = ['size' => 5, 'mtime' => 25, 'mimetype' => 'foo/file'];
		$this->cache->put($file4, $fileData['unkownSize']);

		$this->assertEquals(1025, $this->cache->calculateFolderSize($folder));

		$this->cache->remove($file2);
		$this->cache->remove($file3);
		$this->cache->remove($file4);
		$this->assertEquals(0, $this->cache->calculateFolderSize($folder));

		$this->cache->remove($folder);
		$this->assertFalse($this->cache->inCache($folder . '/foo'));
		$this->assertFalse($this->cache->inCache($folder . '/bar'));
	}

	public function testRemoveRecursive() {
		$folderData = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];
		$fileData = ['size' => 1000, 'mtime' => 20, 'mimetype' => 'text/plain'];
		$folders = ['folder', 'folder/subfolder', 'folder/sub2', 'folder/sub2/sub3'];
		$files = ['folder/foo.txt', 'folder/bar.txt', 'folder/subfolder/asd.txt', 'folder/sub2/qwerty.txt', 'folder/sub2/sub3/foo.txt'];

		foreach ($folders as $folder) {
			$this->cache->put($folder, $folderData);
		}
		foreach ($files as $file) {
			$this->cache->put($file, $fileData);
		}

		$this->cache->remove('folder');
		foreach ($files as $file) {
			$this->assertFalse($this->cache->inCache($file));
		}
	}

	public function folderDataProvider() {
		return [
			['folder'],
			// that was too easy, try something harder
			['☺, WHITE SMILING FACE, UTF-8 hex E298BA'],
			// what about 4 byte utf-8
			['😐, NEUTRAL_FACE, UTF-8 hex F09F9890'],
			// now the crazy stuff
			[', UNASSIGNED PRIVATE USE, UTF-8 hex EF9890'],
			// and my favorite
			['w͢͢͝h͡o͢͡ ̸͢k̵͟n̴͘ǫw̸̛s͘ ̀́w͘͢ḩ̵a҉̡͢t ̧̕h́o̵r͏̵rors̡ ̶͡͠lį̶e͟͟ ̶͝in͢ ͏t̕h̷̡͟e ͟͟d̛a͜r̕͡k̢̨ ͡h̴e͏a̷̢̡rt́͏ ̴̷͠ò̵̶f̸ u̧͘ní̛͜c͢͏o̷͏d̸͢e̡͝']
		];
	}

	public function testEncryptedFolder() {
		$file1 = 'folder';
		$file2 = 'folder/bar';
		$file3 = 'folder/foo';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];
		$fileData = [];
		$fileData['bar'] = ['size' => 1000, 'encrypted' => 1, 'mtime' => 20, 'mimetype' => 'foo/file'];
		$fileData['foo'] = ['size' => 20, 'encrypted' => 1, 'mtime' => 25, 'mimetype' => 'foo/file'];

		$this->cache->put($file1, $data1);
		$this->cache->put($file2, $fileData['bar']);
		$this->cache->put($file3, $fileData['foo']);

		$content = $this->cache->getFolderContents($file1);
		$this->assertEquals(count($content), 2);
		foreach ($content as $cachedData) {
			$data = $fileData[$cachedData['name']];
		}

		$file4 = 'folder/unkownSize';
		$fileData['unkownSize'] = ['size' => -1, 'mtime' => 25, 'mimetype' => 'foo/file'];
		$this->cache->put($file4, $fileData['unkownSize']);

		$this->assertEquals(-1, $this->cache->calculateFolderSize($file1));

		$fileData['unkownSize'] = ['size' => 5, 'mtime' => 25, 'mimetype' => 'foo/file'];
		$this->cache->put($file4, $fileData['unkownSize']);

		$this->assertEquals(1025, $this->cache->calculateFolderSize($file1));
		// direct cache entry retrieval returns the original values
		$entry = $this->cache->get($file1);
		$this->assertEquals(1025, $entry['size']);

		$this->cache->remove($file2);
		$this->cache->remove($file3);
		$this->cache->remove($file4);
		$this->assertEquals(0, $this->cache->calculateFolderSize($file1));

		$this->cache->remove('folder');
		$this->assertFalse($this->cache->inCache('folder/foo'));
		$this->assertFalse($this->cache->inCache('folder/bar'));
	}

	public function testRootFolderSizeForNonHomeStorage() {
		$dir1 = 'knownsize';
		$dir2 = 'unknownsize';
		$fileData = [];
		$fileData[''] = ['size' => -1, 'mtime' => 20, 'mimetype' => 'httpd/unix-directory'];
		$fileData[$dir1] = ['size' => 1000, 'mtime' => 20, 'mimetype' => 'httpd/unix-directory'];
		$fileData[$dir2] = ['size' => -1, 'mtime' => 25, 'mimetype' => 'httpd/unix-directory'];

		$this->cache->put('', $fileData['']);
		$this->cache->put($dir1, $fileData[$dir1]);
		$this->cache->put($dir2, $fileData[$dir2]);

		$this->assertTrue($this->cache->inCache($dir1));
		$this->assertTrue($this->cache->inCache($dir2));

		// check that root size ignored the unknown sizes
		$this->assertEquals(-1, $this->cache->calculateFolderSize(''));

		// clean up
		$this->cache->remove('');
		$this->cache->remove($dir1);
		$this->cache->remove($dir2);

		$this->assertFalse($this->cache->inCache($dir1));
		$this->assertFalse($this->cache->inCache($dir2));
	}

	public function testStatus() {
		$this->assertEquals(\OC\Files\Cache\Cache::NOT_FOUND, $this->cache->getStatus('foo'));
		$this->cache->put('foo', ['size' => -1]);
		$this->assertEquals(\OC\Files\Cache\Cache::PARTIAL, $this->cache->getStatus('foo'));
		$this->cache->put('foo', ['size' => -1, 'mtime' => 20, 'mimetype' => 'foo/file']);
		$this->assertEquals(\OC\Files\Cache\Cache::SHALLOW, $this->cache->getStatus('foo'));
		$this->cache->put('foo', ['size' => 10]);
		$this->assertEquals(\OC\Files\Cache\Cache::COMPLETE, $this->cache->getStatus('foo'));
	}

	public function putWithAllKindOfQuotesData() {
		return [
			['`backtick`'],
			['´forward´'],
			['\'single\''],
		];
	}

	/**
	 * @dataProvider putWithAllKindOfQuotesData
	 * @param $fileName
	 */
	public function testPutWithAllKindOfQuotes($fileName) {
		$this->assertEquals(\OC\Files\Cache\Cache::NOT_FOUND, $this->cache->get($fileName));
		$this->cache->put($fileName, ['size' => 20, 'mtime' => 25, 'mimetype' => 'foo/file', 'etag' => $fileName]);

		$cacheEntry = $this->cache->get($fileName);
		$this->assertEquals($fileName, $cacheEntry['etag']);
		$this->assertEquals($fileName, $cacheEntry['path']);
	}

	public function testSearch() {
		$file1 = 'folder';
		$file2 = 'folder/foobar';
		$file3 = 'folder/foo';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'foo/folder'];
		$fileData = [];
		$fileData['foobar'] = ['size' => 1000, 'mtime' => 20, 'mimetype' => 'foo/file'];
		$fileData['foo'] = ['size' => 20, 'mtime' => 25, 'mimetype' => 'foo/file'];

		$this->cache->put($file1, $data1);
		$this->cache->put($file2, $fileData['foobar']);
		$this->cache->put($file3, $fileData['foo']);

		$this->assertEquals(2, count($this->cache->search('%foo%')));
		$this->assertEquals(1, count($this->cache->search('foo')));
		$this->assertEquals(1, count($this->cache->search('%folder%')));
		$this->assertEquals(1, count($this->cache->search('folder%')));
		$this->assertEquals(3, count($this->cache->search('%')));

		// case insensitive search should match the same files
		$this->assertEquals(2, count($this->cache->search('%Foo%')));
		$this->assertEquals(1, count($this->cache->search('Foo')));
		$this->assertEquals(1, count($this->cache->search('%Folder%')));
		$this->assertEquals(1, count($this->cache->search('Folder%')));

		$this->assertEquals(3, count($this->cache->searchByMime('foo')));
		$this->assertEquals(2, count($this->cache->searchByMime('foo/file')));
	}

	public function testSearchQueryByTag() {
		$userId = static::getUniqueID('user');
		\OC::$server->getUserManager()->createUser($userId, $userId);
		static::loginAsUser($userId);
		$user = new \OC\User\User($userId, null, \OC::$server->getEventDispatcher());

		$file1 = 'folder';
		$file2 = 'folder/foobar';
		$file3 = 'folder/foo';
		$file4 = 'folder/foo2';
		$file5 = 'folder/foo3';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'foo/folder'];
		$fileData = [];
		$fileData['foobar'] = ['size' => 1000, 'mtime' => 20, 'mimetype' => 'foo/file'];
		$fileData['foo'] = ['size' => 20, 'mtime' => 25, 'mimetype' => 'foo/file'];
		$fileData['foo2'] = ['size' => 25, 'mtime' => 28, 'mimetype' => 'foo/file'];
		$fileData['foo3'] = ['size' => 88, 'mtime' => 34, 'mimetype' => 'foo/file'];

		$id1 = $this->cache->put($file1, $data1);
		$id2 = $this->cache->put($file2, $fileData['foobar']);
		$id3 = $this->cache->put($file3, $fileData['foo']);
		$id4 = $this->cache->put($file4, $fileData['foo2']);
		$id5 = $this->cache->put($file5, $fileData['foo3']);

		$tagManager = \OC::$server->getTagManager()->load('files', [], false, $userId);
		$this->assertTrue($tagManager->tagAs($id1, 'tag1'));
		$this->assertTrue($tagManager->tagAs($id1, 'tag2'));
		$this->assertTrue($tagManager->tagAs($id2, 'tag2'));
		$this->assertTrue($tagManager->tagAs($id3, 'tag1'));
		$this->assertTrue($tagManager->tagAs($id4, 'tag2'));

		$results = $this->cache->searchQuery(new SearchQuery(
			new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'tagname', 'tag2'),
			0, 0, [], $user
		));
		$this->assertEquals(3, count($results));

		usort($results, function ($value1, $value2) {
			return $value1['name'] >= $value2['name'];
		});

		$this->assertEquals('folder', $results[0]['name']);
		$this->assertEquals('foo2', $results[1]['name']);
		$this->assertEquals('foobar', $results[2]['name']);

		$tagManager->delete('tag1');
		$tagManager->delete('tag2');

		static::logout();
		$user = \OC::$server->getUserManager()->get($userId);
		if ($user !== null) {
			$user->delete();
		}
	}

	public function testSearchByQuery() {
		$file1 = 'folder';
		$file2 = 'folder/foobar';
		$file3 = 'folder/foo';
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'foo/folder'];
		$fileData = [];
		$fileData['foobar'] = ['size' => 1000, 'mtime' => 20, 'mimetype' => 'foo/file'];
		$fileData['foo'] = ['size' => 20, 'mtime' => 25, 'mimetype' => 'foo/file'];

		$this->cache->put($file1, $data1);
		$this->cache->put($file2, $fileData['foobar']);
		$this->cache->put($file3, $fileData['foo']);
		/** @var IUser $user */
		$user = $this->createMock(IUser::class);

		$this->assertCount(1, $this->cache->searchQuery(new SearchQuery(
			new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'name', 'foo')
			, 10, 0, [], $user)));
		$this->assertCount(2, $this->cache->searchQuery(new SearchQuery(
			new SearchComparison(ISearchComparison::COMPARE_LIKE, 'name', 'foo%')
			, 10, 0, [], $user)));
		$this->assertCount(2, $this->cache->searchQuery(new SearchQuery(
			new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'mimetype', 'foo/file')
			, 10, 0, [], $user)));
		$this->assertCount(3, $this->cache->searchQuery(new SearchQuery(
			new SearchComparison(ISearchComparison::COMPARE_LIKE, 'mimetype', 'foo/%')
			, 10, 0, [], $user)));
		$this->assertCount(1, $this->cache->searchQuery(new SearchQuery(
			new SearchComparison(ISearchComparison::COMPARE_GREATER_THAN, 'size', 100)
			, 10, 0, [], $user)));
		$this->assertCount(2, $this->cache->searchQuery(new SearchQuery(
			new SearchComparison(ISearchComparison::COMPARE_GREATER_THAN_EQUAL, 'size', 100)
			, 10, 0, [], $user)));
	}

	public function movePathProvider() {
		return [
			['folder/foo', 'folder/foobar', ['1', '2']],
			['folder/foo', 'foo', ['1', '2']],
			['files/Индустрия_Инженерные системы ЦОД', 'files/Индустрия_Инженерные системы ЦОД1', ['1', '2']],
		];
	}

	/**
	 * @dataProvider movePathProvider
	 */
	public function testMove($sourceFolder, $targetFolder, $children) {
		$data = ['size' => 100, 'mtime' => 50, 'mimetype' => 'foo/bar'];
		$folderData = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];

		// create folders
		foreach ([$sourceFolder, $targetFolder] as $current) {
			while (strpos($current, '/') > 0) {
				$current = dirname($current);
				$this->cache->put($current, $folderData);
				$this->cache2->put($current, $folderData);
			}
		}

		$this->cache->put($sourceFolder, $folderData);
		$this->cache2->put($sourceFolder, $folderData);
		foreach ($children as $child) {
			$this->cache->put($sourceFolder . '/' . $child, $data);
			$this->cache2->put($sourceFolder . '/' . $child, $data);
		}

		$this->cache->move($sourceFolder, $targetFolder);


		$this->assertFalse($this->cache->inCache($sourceFolder));
		$this->assertTrue($this->cache2->inCache($sourceFolder));
		$this->assertTrue($this->cache->inCache($targetFolder));
		$this->assertFalse($this->cache2->inCache($targetFolder));
		foreach ($children as $child) {
			$this->assertFalse($this->cache->inCache($sourceFolder . '/' . $child));
			$this->assertTrue($this->cache2->inCache($sourceFolder . '/' . $child));
			$this->assertTrue($this->cache->inCache($targetFolder . '/' . $child));
			$this->assertFalse($this->cache2->inCache($targetFolder . '/' . $child));
		}
	}

	public function testGetIncomplete() {
		$file1 = 'folder1';
		$file2 = 'folder2';
		$file3 = 'folder3';
		$file4 = 'folder4';
		$data = ['size' => 10, 'mtime' => 50, 'mimetype' => 'foo/bar'];

		$this->cache->put($file1, $data);
		$data['size'] = -1;
		$this->cache->put($file2, $data);
		$this->cache->put($file3, $data);
		$data['size'] = 12;
		$this->cache->put($file4, $data);

		$this->assertEquals($file3, $this->cache->getIncomplete());
	}

	public function testNonExisting() {
		$this->assertFalse($this->cache->get('foo.txt'));
		$this->assertFalse($this->cache->get(-1));
		$this->assertEquals([], $this->cache->getFolderContents('foo'));
	}

	public function testGetById() {
		$storageId = $this->storage->getId();
		$data = ['size' => 1000, 'mtime' => 20, 'mimetype' => 'foo/file'];
		$id = $this->cache->put('foo', $data);

		if (strlen($storageId) > 64) {
			$storageId = md5($storageId);
		}
		$this->assertEquals([$storageId, 'foo'], \OC\Files\Cache\Cache::getById($id));
	}

	public function testStorageMTime() {
		$data = ['size' => 1000, 'mtime' => 20, 'mimetype' => 'foo/file'];
		$this->cache->put('foo', $data);
		$cachedData = $this->cache->get('foo');
		$this->assertEquals($data['mtime'], $cachedData['storage_mtime']); //if no storage_mtime is saved, mtime should be used

		$this->cache->put('foo', ['storage_mtime' => 30]); //when setting storage_mtime, mtime is also set
		$cachedData = $this->cache->get('foo');
		$this->assertEquals(30, $cachedData['storage_mtime']);
		$this->assertEquals(30, $cachedData['mtime']);

		$this->cache->put('foo', ['mtime' => 25]); //setting mtime does not change storage_mtime
		$cachedData = $this->cache->get('foo');
		$this->assertEquals(30, $cachedData['storage_mtime']);
		$this->assertEquals(25, $cachedData['mtime']);
	}

	public function testLongId() {
		$storage = new LongId([]);
		$cache = $storage->getCache();
		$storageId = $storage->getId();
		$data = ['size' => 1000, 'mtime' => 20, 'mimetype' => 'foo/file'];
		$id = $cache->put('foo', $data);
		$this->assertEquals([md5($storageId), 'foo'], \OC\Files\Cache\Cache::getById($id));
	}

	/**
	 * this test show the bug resulting if we have no normalizer installed
	 */
	public function testWithoutNormalizer() {
		// folder name "Schön" with U+00F6 (normalized)
		$folderWith00F6 = "\x53\x63\x68\xc3\xb6\x6e";

		// folder name "Schön" with U+0308 (un-normalized)
		$folderWith0308 = "\x53\x63\x68\x6f\xcc\x88\x6e";

		/**
		 * @var \OC\Files\Cache\Cache | \PHPUnit\Framework\MockObject\MockObject $cacheMock
		 */
		$cacheMock = $this->getMockBuilder(Cache::class)
			->setMethods(['normalize'])
			->setConstructorArgs([$this->storage])
			->getMock();

		$cacheMock->expects($this->any())
			->method('normalize')
			->willReturnArgument(0);

		$data = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];

		// put root folder
		$this->assertFalse($cacheMock->get('folder'));
		$this->assertGreaterThan(0, $cacheMock->put('folder', $data));

		// put un-normalized folder
		$this->assertFalse($cacheMock->get('folder/' . $folderWith0308));
		$this->assertGreaterThan(0, $cacheMock->put('folder/' . $folderWith0308, $data));

		// get un-normalized folder by name
		$unNormalizedFolderName = $cacheMock->get('folder/' . $folderWith0308);

		// check if database layer normalized the folder name (this should not happen)
		$this->assertEquals($folderWith0308, $unNormalizedFolderName['name']);

		// put normalized folder
		$this->assertFalse($cacheMock->get('folder/' . $folderWith00F6));
		$this->assertGreaterThan(0, $cacheMock->put('folder/' . $folderWith00F6, $data));

		// this is our bug, we have two different hashes with the same name (Schön)
		$this->assertEquals(2, count($cacheMock->getFolderContents('folder')));
	}

	/**
	 * this test shows that there is no bug if we use the normalizer
	 */
	public function testWithNormalizer() {
		if (!class_exists('Patchwork\PHP\Shim\Normalizer')) {
			$this->markTestSkipped('The 3rdparty Normalizer extension is not available.');
			return;
		}

		// folder name "Schön" with U+00F6 (normalized)
		$folderWith00F6 = "\x53\x63\x68\xc3\xb6\x6e";

		// folder name "Schön" with U+0308 (un-normalized)
		$folderWith0308 = "\x53\x63\x68\x6f\xcc\x88\x6e";

		$data = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];

		// put root folder
		$this->assertFalse($this->cache->get('folder'));
		$this->assertGreaterThan(0, $this->cache->put('folder', $data));

		// put un-normalized folder
		$this->assertFalse($this->cache->get('folder/' . $folderWith0308));
		$this->assertGreaterThan(0, $this->cache->put('folder/' . $folderWith0308, $data));

		// get un-normalized folder by name
		$unNormalizedFolderName = $this->cache->get('folder/' . $folderWith0308);

		// check if folder name was normalized
		$this->assertEquals($folderWith00F6, $unNormalizedFolderName['name']);

		// put normalized folder
		$this->assertInstanceOf('\OCP\Files\Cache\ICacheEntry', $this->cache->get('folder/' . $folderWith00F6));
		$this->assertGreaterThan(0, $this->cache->put('folder/' . $folderWith00F6, $data));

		// at this point we should have only one folder named "Schön"
		$this->assertEquals(1, count($this->cache->getFolderContents('folder')));
	}

	public function bogusPathNamesProvider() {
		return [
			['/bogus.txt', 'bogus.txt'],
			['//bogus.txt', 'bogus.txt'],
			['bogus/', 'bogus'],
			['bogus//', 'bogus'],
		];
	}

	/**
	 * Test bogus paths with leading or doubled slashes
	 *
	 * @dataProvider bogusPathNamesProvider
	 */
	public function testBogusPaths($bogusPath, $fixedBogusPath) {
		$data = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];

		// put root folder
		$this->assertFalse($this->cache->get(''));
		$parentId = $this->cache->put('', $data);
		$this->assertGreaterThan(0, $parentId);

		$this->assertGreaterThan(0, $this->cache->put($bogusPath, $data));

		$newData = $this->cache->get($fixedBogusPath);
		$this->assertNotFalse($newData);

		$this->assertEquals($fixedBogusPath, $newData['path']);
		// parent is the correct one, resolved properly (they used to not be)
		$this->assertEquals($parentId, $newData['parent']);

		$newDataFromBogus = $this->cache->get($bogusPath);
		// same entry
		$this->assertEquals($newData, $newDataFromBogus);
	}

	public function testNoReuseOfFileId() {
		$data1 = ['size' => 100, 'mtime' => 50, 'mimetype' => 'text/plain'];
		$this->cache->put('somefile.txt', $data1);
		$info = $this->cache->get('somefile.txt');
		$fileId = $info['fileid'];
		$this->cache->remove('somefile.txt');
		$data2 = ['size' => 200, 'mtime' => 100, 'mimetype' => 'text/plain'];
		$this->cache->put('anotherfile.txt', $data2);
		$info2 = $this->cache->get('anotherfile.txt');
		$fileId2 = $info2['fileid'];
		$this->assertNotEquals($fileId, $fileId2);
	}

	public function escapingProvider() {
		return [
			['foo'],
			['o%'],
			['oth_r'],
		];
	}

	/**
	 * @param string $name
	 * @dataProvider escapingProvider
	 */
	public function testEscaping($name) {
		$data = ['size' => 100, 'mtime' => 50, 'mimetype' => 'text/plain'];
		$this->cache->put($name, $data);
		$this->assertTrue($this->cache->inCache($name));
		$retrievedData = $this->cache->get($name);
		foreach ($data as $key => $value) {
			$this->assertEquals($value, $retrievedData[$key]);
		}
		$this->cache->move($name, $name . 'asd');
		$this->assertFalse($this->cache->inCache($name));
		$this->assertTrue($this->cache->inCache($name . 'asd'));
		$this->cache->remove($name . 'asd');
		$this->assertFalse($this->cache->inCache($name . 'asd'));
		$folderData = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];
		$this->cache->put($name, $folderData);
		$this->cache->put('other', $folderData);
		$childs = ['asd', 'bar', 'foo', 'sub/folder'];
		$this->cache->put($name . '/sub', $folderData);
		$this->cache->put('other/sub', $folderData);
		foreach ($childs as $child) {
			$this->cache->put($name . '/' . $child, $data);
			$this->cache->put('other/' . $child, $data);
			$this->assertTrue($this->cache->inCache($name . '/' . $child));
		}
		$this->cache->move($name, $name . 'asd');
		foreach ($childs as $child) {
			$this->assertTrue($this->cache->inCache($name . 'asd/' . $child));
			$this->assertTrue($this->cache->inCache('other/' . $child));
		}
		foreach ($childs as $child) {
			$this->cache->remove($name . 'asd/' . $child);
			$this->assertFalse($this->cache->inCache($name . 'asd/' . $child));
			$this->assertTrue($this->cache->inCache('other/' . $child));
		}
	}

	public function testExtended() {
		$folderData = ['size' => 100, 'mtime' => 50, 'mimetype' => 'httpd/unix-directory'];
		$this->cache->put("", $folderData);

		$data = ['size' => 100, 'mtime' => 50, 'mimetype' => 'text/plain', 'creation_time' => 20];
		$id1 = $this->cache->put("foo1", $data);
		$data = ['size' => 100, 'mtime' => 50, 'mimetype' => 'text/plain', 'upload_time' => 30];
		$this->cache->put("foo2", $data);
		$data = ['size' => 100, 'mtime' => 50, 'mimetype' => 'text/plain', 'metadata_etag' => 'foo'];
		$this->cache->put("foo3", $data);
		$data = ['size' => 100, 'mtime' => 50, 'mimetype' => 'text/plain'];
		$id4 = $this->cache->put("foo4", $data);

		$entry = $this->cache->get($id1);
		$this->assertEquals(20, $entry->getCreationTime());
		$this->assertEquals(0, $entry->getUploadTime());
		$this->assertEquals(null, $entry->getMetadataEtag());

		$entries = $this->cache->getFolderContents("");
		$this->assertCount(4, $entries);

		$this->assertEquals("foo1", $entries[0]->getName());
		$this->assertEquals("foo2", $entries[1]->getName());
		$this->assertEquals("foo3", $entries[2]->getName());
		$this->assertEquals("foo4", $entries[3]->getName());

		$this->assertEquals(20, $entries[0]->getCreationTime());
		$this->assertEquals(0, $entries[0]->getUploadTime());
		$this->assertEquals(null, $entries[0]->getMetadataEtag());

		$this->assertEquals(0, $entries[1]->getCreationTime());
		$this->assertEquals(30, $entries[1]->getUploadTime());
		$this->assertEquals(null, $entries[1]->getMetadataEtag());

		$this->assertEquals(0, $entries[2]->getCreationTime());
		$this->assertEquals(0, $entries[2]->getUploadTime());
		$this->assertEquals('foo', $entries[2]->getMetadataEtag());

		$this->assertEquals(0, $entries[3]->getCreationTime());
		$this->assertEquals(0, $entries[3]->getUploadTime());
		$this->assertEquals(null, $entries[3]->getMetadataEtag());

		$this->cache->update($id1, ['upload_time' => 25]);

		$entry = $this->cache->get($id1);
		$this->assertEquals(20, $entry->getCreationTime());
		$this->assertEquals(25, $entry->getUploadTime());
		$this->assertEquals(null, $entry->getMetadataEtag());

		$this->cache->put("sub", $folderData);

		$this->cache->move("foo1", "sub/foo1");

		$entries = $this->cache->getFolderContents("sub");
		$this->assertCount(1, $entries);

		$this->assertEquals(20, $entries[0]->getCreationTime());
		$this->assertEquals(25, $entries[0]->getUploadTime());
		$this->assertEquals(null, $entries[0]->getMetadataEtag());

		$this->cache->update($id4, ['upload_time' => 25]);

		$entry = $this->cache->get($id4);
		$this->assertEquals(0, $entry->getCreationTime());
		$this->assertEquals(25, $entry->getUploadTime());
		$this->assertEquals(null, $entry->getMetadataEtag());

		$this->cache->remove("sub");
	}

	protected function tearDown(): void {
		if ($this->cache) {
			$this->cache->clear();
		}

		parent::tearDown();
	}

	protected function setUp(): void {
		parent::setUp();

		$this->storage = new \OC\Files\Storage\Temporary([]);
		$this->storage2 = new \OC\Files\Storage\Temporary([]);
		$this->cache = new \OC\Files\Cache\Cache($this->storage);
		$this->cache2 = new \OC\Files\Cache\Cache($this->storage2);
	}
}
