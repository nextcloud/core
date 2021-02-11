<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Markus Goetz <markus@woboq.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre;

use OC\User\User;
use OCA\DAV\Connector\Sabre\Directory;
use OCA\DAV\Connector\Sabre\File;
use OCA\DAV\Connector\Sabre\FilesPlugin;
use OCA\DAV\Connector\Sabre\Node;
use OCP\Files\FileInfo;
use OCP\Files\StorageNotAvailableException;
use OCP\IConfig;
use OCP\IPreview;
use OCP\IRequest;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Server;
use Sabre\DAV\Tree;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\Xml\Service;
use Test\TestCase;

/**
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
class FilesPluginTest extends TestCase {
	public const GETETAG_PROPERTYNAME = FilesPlugin::GETETAG_PROPERTYNAME;
	public const FILEID_PROPERTYNAME = FilesPlugin::FILEID_PROPERTYNAME;
	public const INTERNAL_FILEID_PROPERTYNAME = FilesPlugin::INTERNAL_FILEID_PROPERTYNAME;
	public const SIZE_PROPERTYNAME = FilesPlugin::SIZE_PROPERTYNAME;
	public const PERMISSIONS_PROPERTYNAME = FilesPlugin::PERMISSIONS_PROPERTYNAME;
	public const LASTMODIFIED_PROPERTYNAME = FilesPlugin::LASTMODIFIED_PROPERTYNAME;
	public const DOWNLOADURL_PROPERTYNAME = FilesPlugin::DOWNLOADURL_PROPERTYNAME;
	public const OWNER_ID_PROPERTYNAME = FilesPlugin::OWNER_ID_PROPERTYNAME;
	public const OWNER_DISPLAY_NAME_PROPERTYNAME = FilesPlugin::OWNER_DISPLAY_NAME_PROPERTYNAME;
	public const DATA_FINGERPRINT_PROPERTYNAME = FilesPlugin::DATA_FINGERPRINT_PROPERTYNAME;
	public const HAS_PREVIEW_PROPERTYNAME = FilesPlugin::HAS_PREVIEW_PROPERTYNAME;

	/**
	 * @var \Sabre\DAV\Server | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $server;

	/**
	 * @var \Sabre\DAV\Tree | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $tree;

	/**
	 * @var FilesPlugin
	 */
	private $plugin;

	/**
	 * @var \OCP\IConfig | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $config;

	/**
	 * @var \OCP\IRequest | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $request;

	/**
	 * @var \OCP\IPreview | \PHPUnit\Framework\MockObject\MockObject
	 */
	private $previewManager;

	protected function setUp(): void {
		parent::setUp();
		$this->server = $this->getMockBuilder(Server::class)
			->disableOriginalConstructor()
			->getMock();
		$this->tree = $this->getMockBuilder(Tree::class)
			->disableOriginalConstructor()
			->getMock();
		$this->config = $this->createMock(IConfig::class);
		$this->config->expects($this->any())->method('getSystemValue')
			->with($this->equalTo('data-fingerprint'), $this->equalTo(''))
			->willReturn('my_fingerprint');
		$this->request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();
		$this->previewManager = $this->getMockBuilder(IPreview::class)
			->disableOriginalConstructor()
			->getMock();

		$this->plugin = new FilesPlugin(
			$this->tree,
			$this->config,
			$this->request,
			$this->previewManager
		);

		$response = $this->getMockBuilder(ResponseInterface::class)
				->disableOriginalConstructor()
				->getMock();
		$this->server->httpResponse = $response;
		$this->server->xml = new Service();

		$this->plugin->initialize($this->server);
	}

	/**
	 * @param string $class
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	private function createTestNode($class, $path = '/dummypath') {
		$node = $this->getMockBuilder($class)
			->disableOriginalConstructor()
			->getMock();

		$node->expects($this->any())
			->method('getId')
			->willReturn(123);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with($path)
			->willReturn($node);

		$node->expects($this->any())
			->method('getFileId')
			->willReturn('00000123instanceid');
		$node->expects($this->any())
			->method('getInternalFileId')
			->willReturn('123');
		$node->expects($this->any())
			->method('getEtag')
			->willReturn('"abc"');
		$node->expects($this->any())
			->method('getDavPermissions')
			->willReturn('DWCKMSR');

		$fileInfo = $this->createMock(FileInfo::class);
		$fileInfo->expects($this->any())
			->method('isReadable')
			->willReturn(true);

		$node->expects($this->any())
			->method('getFileInfo')
			->willReturn($fileInfo);

		return $node;
	}

	public function testGetPropertiesForFile() {
		/** @var \OCA\DAV\Connector\Sabre\File | \PHPUnit\Framework\MockObject\MockObject $node */
		$node = $this->createTestNode('\OCA\DAV\Connector\Sabre\File');

		$propFind = new PropFind(
			'/dummyPath',
			[
				self::GETETAG_PROPERTYNAME,
				self::FILEID_PROPERTYNAME,
				self::INTERNAL_FILEID_PROPERTYNAME,
				self::SIZE_PROPERTYNAME,
				self::PERMISSIONS_PROPERTYNAME,
				self::DOWNLOADURL_PROPERTYNAME,
				self::OWNER_ID_PROPERTYNAME,
				self::OWNER_DISPLAY_NAME_PROPERTYNAME,
				self::DATA_FINGERPRINT_PROPERTYNAME,
			],
			0
		);

		$user = $this->getMockBuilder(User::class)
			->disableOriginalConstructor()->getMock();
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('foo');
		$user
			->expects($this->once())
			->method('getDisplayName')
			->willReturn('M. Foo');

		$node->expects($this->once())
			->method('getDirectDownload')
			->willReturn(['url' => 'http://example.com/']);
		$node->expects($this->exactly(2))
			->method('getOwner')
			->willReturn($user);

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);

		$this->assertEquals('"abc"', $propFind->get(self::GETETAG_PROPERTYNAME));
		$this->assertEquals('00000123instanceid', $propFind->get(self::FILEID_PROPERTYNAME));
		$this->assertEquals('123', $propFind->get(self::INTERNAL_FILEID_PROPERTYNAME));
		$this->assertEquals(null, $propFind->get(self::SIZE_PROPERTYNAME));
		$this->assertEquals('DWCKMSR', $propFind->get(self::PERMISSIONS_PROPERTYNAME));
		$this->assertEquals('http://example.com/', $propFind->get(self::DOWNLOADURL_PROPERTYNAME));
		$this->assertEquals('foo', $propFind->get(self::OWNER_ID_PROPERTYNAME));
		$this->assertEquals('M. Foo', $propFind->get(self::OWNER_DISPLAY_NAME_PROPERTYNAME));
		$this->assertEquals('my_fingerprint', $propFind->get(self::DATA_FINGERPRINT_PROPERTYNAME));
		$this->assertEquals([self::SIZE_PROPERTYNAME], $propFind->get404Properties());
	}

	public function testGetPropertiesStorageNotAvailable() {
		/** @var \OCA\DAV\Connector\Sabre\File | \PHPUnit\Framework\MockObject\MockObject $node */
		$node = $this->createTestNode('\OCA\DAV\Connector\Sabre\File');

		$propFind = new PropFind(
			'/dummyPath',
			[
				self::DOWNLOADURL_PROPERTYNAME,
			],
			0
		);

		$node->expects($this->once())
			->method('getDirectDownload')
			->will($this->throwException(new StorageNotAvailableException()));

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);

		$this->assertEquals(null, $propFind->get(self::DOWNLOADURL_PROPERTYNAME));
	}

	public function testGetPublicPermissions() {
		$this->plugin = new FilesPlugin(
			$this->tree,
			$this->config,
			$this->getMockBuilder(IRequest::class)
				->disableOriginalConstructor()
				->getMock(),
			$this->previewManager,
			true);
		$this->plugin->initialize($this->server);

		$propFind = new PropFind(
			'/dummyPath',
			[
				self::PERMISSIONS_PROPERTYNAME,
			],
			0
		);

		/** @var \OCA\DAV\Connector\Sabre\File | \PHPUnit\Framework\MockObject\MockObject $node */
		$node = $this->createTestNode('\OCA\DAV\Connector\Sabre\File');
		$node->expects($this->any())
			->method('getDavPermissions')
			->willReturn('DWCKMSR');

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);

		$this->assertEquals('DWCKR', $propFind->get(self::PERMISSIONS_PROPERTYNAME));
	}

	public function testGetPropertiesForDirectory() {
		/** @var \OCA\DAV\Connector\Sabre\Directory | \PHPUnit\Framework\MockObject\MockObject $node */
		$node = $this->createTestNode('\OCA\DAV\Connector\Sabre\Directory');

		$propFind = new PropFind(
			'/dummyPath',
			[
				self::GETETAG_PROPERTYNAME,
				self::FILEID_PROPERTYNAME,
				self::SIZE_PROPERTYNAME,
				self::PERMISSIONS_PROPERTYNAME,
				self::DOWNLOADURL_PROPERTYNAME,
				self::DATA_FINGERPRINT_PROPERTYNAME,
			],
			0
		);

		$node->expects($this->once())
			->method('getSize')
			->willReturn(1025);

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);

		$this->assertEquals('"abc"', $propFind->get(self::GETETAG_PROPERTYNAME));
		$this->assertEquals('00000123instanceid', $propFind->get(self::FILEID_PROPERTYNAME));
		$this->assertEquals(1025, $propFind->get(self::SIZE_PROPERTYNAME));
		$this->assertEquals('DWCKMSR', $propFind->get(self::PERMISSIONS_PROPERTYNAME));
		$this->assertEquals(null, $propFind->get(self::DOWNLOADURL_PROPERTYNAME));
		$this->assertEquals('my_fingerprint', $propFind->get(self::DATA_FINGERPRINT_PROPERTYNAME));
		$this->assertEquals([self::DOWNLOADURL_PROPERTYNAME], $propFind->get404Properties());
	}

	public function testGetPropertiesForRootDirectory() {
		/** @var \OCA\DAV\Connector\Sabre\Directory|\PHPUnit\Framework\MockObject\MockObject $node */
		$node = $this->getMockBuilder(Directory::class)
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->any())->method('getPath')->willReturn('/');

		$fileInfo = $this->createMock(FileInfo::class);
		$fileInfo->expects($this->any())
			->method('isReadable')
			->willReturn(true);

		$node->expects($this->any())
			->method('getFileInfo')
			->willReturn($fileInfo);

		$propFind = new PropFind(
			'/',
			[
				self::DATA_FINGERPRINT_PROPERTYNAME,
			],
			0
		);

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);

		$this->assertEquals('my_fingerprint', $propFind->get(self::DATA_FINGERPRINT_PROPERTYNAME));
	}

	public function testGetPropertiesWhenNoPermission() {
		// No read permissions can be caused by files access control.
		// But we still want to load the directory list, so this is okay for us.
		// $this->expectException(\Sabre\DAV\Exception\NotFound::class);

		/** @var \OCA\DAV\Connector\Sabre\Directory|\PHPUnit\Framework\MockObject\MockObject $node */
		$node = $this->getMockBuilder(Directory::class)
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->any())->method('getPath')->willReturn('/');

		$fileInfo = $this->createMock(FileInfo::class);
		$fileInfo->expects($this->any())
			->method('isReadable')
			->willReturn(false);

		$node->expects($this->any())
			->method('getFileInfo')
			->willReturn($fileInfo);

		$propFind = new PropFind(
			'/test',
			[
				self::DATA_FINGERPRINT_PROPERTYNAME,
			],
			0
		);

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);

		$this->addToAssertionCount(1);
	}

	public function testUpdateProps() {
		$node = $this->createTestNode('\OCA\DAV\Connector\Sabre\File');

		$testDate = 'Fri, 13 Feb 2015 00:01:02 GMT';

		$node->expects($this->once())
			->method('touch')
			->with($testDate);

		$node->expects($this->once())
			->method('setEtag')
			->with('newetag')
			->willReturn(true);

		// properties to set
		$propPatch = new PropPatch([
			self::GETETAG_PROPERTYNAME => 'newetag',
			self::LASTMODIFIED_PROPERTYNAME => $testDate
		]);

		$this->plugin->handleUpdateProperties(
			'/dummypath',
			$propPatch
		);

		$propPatch->commit();

		$this->assertEmpty($propPatch->getRemainingMutations());

		$result = $propPatch->getResult();
		$this->assertEquals(200, $result[self::LASTMODIFIED_PROPERTYNAME]);
		$this->assertEquals(200, $result[self::GETETAG_PROPERTYNAME]);
	}

	public function testUpdatePropsForbidden() {
		$propPatch = new PropPatch([
			self::OWNER_ID_PROPERTYNAME => 'user2',
			self::OWNER_DISPLAY_NAME_PROPERTYNAME => 'User Two',
			self::FILEID_PROPERTYNAME => 12345,
			self::PERMISSIONS_PROPERTYNAME => 'C',
			self::SIZE_PROPERTYNAME => 123,
			self::DOWNLOADURL_PROPERTYNAME => 'http://example.com/',
		]);

		$this->plugin->handleUpdateProperties(
			'/dummypath',
			$propPatch
		);

		$propPatch->commit();

		$this->assertEmpty($propPatch->getRemainingMutations());

		$result = $propPatch->getResult();
		$this->assertEquals(403, $result[self::OWNER_ID_PROPERTYNAME]);
		$this->assertEquals(403, $result[self::OWNER_DISPLAY_NAME_PROPERTYNAME]);
		$this->assertEquals(403, $result[self::FILEID_PROPERTYNAME]);
		$this->assertEquals(403, $result[self::PERMISSIONS_PROPERTYNAME]);
		$this->assertEquals(403, $result[self::SIZE_PROPERTYNAME]);
		$this->assertEquals(403, $result[self::DOWNLOADURL_PROPERTYNAME]);
	}

	/**
	 * Testcase from https://github.com/owncloud/core/issues/5251
	 *
	 * |-FolderA
	 *  |-text.txt
	 * |-test.txt
	 *
	 * FolderA is an incoming shared folder and there are no delete permissions.
	 * Thus moving /FolderA/test.txt to /test.txt should fail already on that check
	 *
	 */
	public function testMoveSrcNotDeletable() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);
		$this->expectExceptionMessage('FolderA/test.txt cannot be deleted');

		$fileInfoFolderATestTXT = $this->getMockBuilder(FileInfo::class)
			->disableOriginalConstructor()
			->getMock();
		$fileInfoFolderATestTXT->expects($this->once())
			->method('isDeletable')
			->willReturn(false);

		$node = $this->getMockBuilder(Node::class)
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->once())
			->method('getFileInfo')
			->willReturn($fileInfoFolderATestTXT);

		$this->tree->expects($this->once())->method('getNodeForPath')
			->willReturn($node);

		$this->plugin->checkMove('FolderA/test.txt', 'test.txt');
	}

	public function testMoveSrcDeletable() {
		$fileInfoFolderATestTXT = $this->getMockBuilder(FileInfo::class)
			->disableOriginalConstructor()
			->getMock();
		$fileInfoFolderATestTXT->expects($this->once())
			->method('isDeletable')
			->willReturn(true);

		$node = $this->getMockBuilder(Node::class)
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->once())
			->method('getFileInfo')
			->willReturn($fileInfoFolderATestTXT);

		$this->tree->expects($this->once())->method('getNodeForPath')
			->willReturn($node);

		$this->plugin->checkMove('FolderA/test.txt', 'test.txt');
	}


	public function testMoveSrcNotExist() {
		$this->expectException(\Sabre\DAV\Exception\NotFound::class);
		$this->expectExceptionMessage('FolderA/test.txt does not exist');

		$node = $this->getMockBuilder(Node::class)
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->once())
			->method('getFileInfo')
			->willReturn(null);

		$this->tree->expects($this->once())->method('getNodeForPath')
			->willReturn($node);

		$this->plugin->checkMove('FolderA/test.txt', 'test.txt');
	}

	public function downloadHeadersProvider() {
		return [
			[
				false,
				'attachment; filename*=UTF-8\'\'somefile.xml; filename="somefile.xml"'
			],
			[
				true,
				'attachment; filename="somefile.xml"'
			],
		];
	}

	/**
	 * @dataProvider downloadHeadersProvider
	 */
	public function testDownloadHeaders($isClumsyAgent, $contentDispositionHeader) {
		$request = $this->getMockBuilder(RequestInterface::class)
			->disableOriginalConstructor()
			->getMock();
		$response = $this->getMockBuilder(ResponseInterface::class)
				->disableOriginalConstructor()
				->getMock();

		$request
			->expects($this->once())
			->method('getPath')
			->willReturn('test/somefile.xml');

		$node = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$node
			->expects($this->once())
			->method('getName')
			->willReturn('somefile.xml');

		$this->tree
			->expects($this->once())
			->method('getNodeForPath')
			->with('test/somefile.xml')
			->willReturn($node);

		$this->request
			->expects($this->once())
			->method('isUserAgent')
			->willReturn($isClumsyAgent);

		$response
			->expects($this->once())
			->method('addHeader')
			->with('Content-Disposition', $contentDispositionHeader);

		$this->plugin->httpGet($request, $response);
	}

	public function testHasPreview() {
		/** @var \OCA\DAV\Connector\Sabre\Directory | \PHPUnit\Framework\MockObject\MockObject $node */
		$node = $this->createTestNode('\OCA\DAV\Connector\Sabre\Directory');

		$propFind = new PropFind(
			'/dummyPath',
			[
				self::HAS_PREVIEW_PROPERTYNAME
			],
			0
		);

		$this->previewManager->expects($this->once())
			->method('isAvailable')
			->willReturn(false);

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);

		$this->assertEquals("false", $propFind->get(self::HAS_PREVIEW_PROPERTYNAME));
	}
}
