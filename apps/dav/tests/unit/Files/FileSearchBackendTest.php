<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\DAV\Tests\Files;

use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchQuery;
use OC\Files\View;
use OCA\DAV\Connector\Sabre\CachingTree;
use OCA\DAV\Connector\Sabre\Directory;
use OCA\DAV\Connector\Sabre\File;
use OCA\DAV\Connector\Sabre\FilesPlugin;
use OCA\DAV\Files\FileSearchBackend;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\Files\Search\ISearchQuery;
use OCP\IUser;
use OCP\Share\IManager;
use SearchDAV\Backend\SearchPropertyDefinition;
use SearchDAV\Query\Limit;
use SearchDAV\Query\Query;
use SearchDAV\XML\Operator;
use Test\TestCase;

class FileSearchBackendTest extends TestCase {
	/** @var CachingTree|\PHPUnit_Framework_MockObject_MockObject */
	private $tree;

	/** @var IUser */
	private $user;

	/** @var IRootFolder|\PHPUnit_Framework_MockObject_MockObject */
	private $rootFolder;

	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;

	/** @var View|\PHPUnit_Framework_MockObject_MockObject */
	private $view;

	/** @var Folder|\PHPUnit_Framework_MockObject_MockObject */
	private $searchFolder;

	/** @var FileSearchBackend */
	private $search;

	/** @var Directory|\PHPUnit_Framework_MockObject_MockObject */
	private $davFolder;

	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock(IUser::class);
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('test');

		$this->tree = $this->getMockBuilder(CachingTree::class)
			->disableOriginalConstructor()
			->getMock();

		$this->view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()
			->getMock();

		$this->view->expects($this->any())
			->method('getRelativePath')
			->willReturnArgument(0);

		$this->rootFolder = $this->createMock(IRootFolder::class);

		$this->shareManager = $this->createMock(IManager::class);

		$this->searchFolder = $this->createMock(Folder::class);

		$fileInfo = $this->createMock(FileInfo::class);

		$this->davFolder = $this->createMock(Directory::class);

		$this->davFolder->expects($this->any())
			->method('getFileInfo')
			->willReturn($fileInfo);

		$this->rootFolder->expects($this->any())
			->method('get')
			->willReturn($this->searchFolder);

		$this->search = new FileSearchBackend($this->tree, $this->user, $this->rootFolder, $this->shareManager, $this->view);
	}

	public function testSearchFilename() {
		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->willReturn($this->davFolder);

		$this->searchFolder->expects($this->once())
			->method('search')
			->with(new SearchQuery(
				new SearchComparison(
					ISearchComparison::COMPARE_EQUAL,
					'name',
					'foo'
				),
				0,
				0,
				[],
				$this->user
			))
			->willReturn([
				new \OC\Files\Node\Folder($this->rootFolder, $this->view, '/test/path')
			]);

		$query = $this->getBasicQuery(\SearchDAV\Query\Operator::OPERATION_EQUAL, '{DAV:}displayname', 'foo');
		$result = $this->search->search($query);

		$this->assertCount(1, $result);
		$this->assertEquals('/files/test/test/path', $result[0]->href);
	}

	public function testSearchMimetype() {
		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->willReturn($this->davFolder);

		$this->searchFolder->expects($this->once())
			->method('search')
			->with(new SearchQuery(
				new SearchComparison(
					ISearchComparison::COMPARE_EQUAL,
					'mimetype',
					'foo'
				),
				0,
				0,
				[],
				$this->user
			))
			->willReturn([
				new \OC\Files\Node\Folder($this->rootFolder, $this->view, '/test/path')
			]);

		$query = $this->getBasicQuery(\SearchDAV\Query\Operator::OPERATION_EQUAL, '{DAV:}getcontenttype', 'foo');
		$result = $this->search->search($query);

		$this->assertCount(1, $result);
		$this->assertEquals('/files/test/test/path', $result[0]->href);
	}

	public function testSearchSize() {
		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->willReturn($this->davFolder);

		$this->searchFolder->expects($this->once())
			->method('search')
			->with(new SearchQuery(
				new SearchComparison(
					ISearchComparison::COMPARE_GREATER_THAN,
					'size',
					10
				),
				0,
				0,
				[],
				$this->user
			))
			->willReturn([
				new \OC\Files\Node\Folder($this->rootFolder, $this->view, '/test/path')
			]);

		$query = $this->getBasicQuery(\SearchDAV\Query\Operator::OPERATION_GREATER_THAN, FilesPlugin::SIZE_PROPERTYNAME, 10);
		$result = $this->search->search($query);

		$this->assertCount(1, $result);
		$this->assertEquals('/files/test/test/path', $result[0]->href);
	}

	public function testSearchMtime() {
		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->willReturn($this->davFolder);

		$this->searchFolder->expects($this->once())
			->method('search')
			->with(new SearchQuery(
				new SearchComparison(
					ISearchComparison::COMPARE_GREATER_THAN,
					'mtime',
					10
				),
				0,
				0,
				[],
				$this->user
			))
			->willReturn([
				new \OC\Files\Node\Folder($this->rootFolder, $this->view, '/test/path')
			]);

		$query = $this->getBasicQuery(\SearchDAV\Query\Operator::OPERATION_GREATER_THAN, '{DAV:}getlastmodified', 10);
		$result = $this->search->search($query);

		$this->assertCount(1, $result);
		$this->assertEquals('/files/test/test/path', $result[0]->href);
	}

	public function testSearchIsCollection() {
		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->willReturn($this->davFolder);

		$this->searchFolder->expects($this->once())
			->method('search')
			->with(new SearchQuery(
				new SearchComparison(
					ISearchComparison::COMPARE_EQUAL,
					'mimetype',
					FileInfo::MIMETYPE_FOLDER
				),
				0,
				0,
				[],
				$this->user
			))
			->willReturn([
				new \OC\Files\Node\Folder($this->rootFolder, $this->view, '/test/path')
			]);

		$query = $this->getBasicQuery(\SearchDAV\Query\Operator::OPERATION_IS_COLLECTION, 'yes');
		$result = $this->search->search($query);

		$this->assertCount(1, $result);
		$this->assertEquals('/files/test/test/path', $result[0]->href);
	}

	
	public function testSearchInvalidProp() {
		$this->expectException(\InvalidArgumentException::class);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->willReturn($this->davFolder);

		$this->searchFolder->expects($this->never())
			->method('search');

		$query = $this->getBasicQuery(\SearchDAV\Query\Operator::OPERATION_EQUAL, '{DAV:}getetag', 'foo');
		$this->search->search($query);
	}

	private function getBasicQuery($type, $property, $value = null) {
		$scope = new \SearchDAV\Query\Scope('/', 'infinite');
		$scope->path = '/';
		$from = [$scope];
		$orderBy = [];
		$select = [];
		if (is_null($value)) {
			$where = new \SearchDAV\Query\Operator(
				$type,
				[new \SearchDAV\Query\Literal($property)]
			);
		} else {
			$where = new \SearchDAV\Query\Operator(
				$type,
				[new SearchPropertyDefinition($property, true, true, true), new \SearchDAV\Query\Literal($value)]
			);
		}
		$limit = new Limit();

		return new Query($select, $from, $where, $orderBy, $limit);
	}

	
	public function testSearchNonFolder() {
		$this->expectException(\InvalidArgumentException::class);

		$davNode = $this->createMock(File::class);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->willReturn($davNode);

		$query = $this->getBasicQuery(\SearchDAV\Query\Operator::OPERATION_EQUAL, '{DAV:}displayname', 'foo');
		$this->search->search($query);
	}

	public function testSearchLimitOwnerBasic() {
		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->willReturn($this->davFolder);

		/** @var ISearchQuery|null $receivedQuery */
		$receivedQuery = null;
		$this->searchFolder
			->method('search')
			->willReturnCallback(function ($query) use (&$receivedQuery) {
				$receivedQuery = $query;
				return [
					new \OC\Files\Node\Folder($this->rootFolder, $this->view, '/test/path')
				];
			});

		$query = $this->getBasicQuery(\SearchDAV\Query\Operator::OPERATION_EQUAL, FilesPlugin::OWNER_ID_PROPERTYNAME, $this->user->getUID());
		$this->search->search($query);

		$this->assertNotNull($receivedQuery);
		$this->assertTrue($receivedQuery->limitToHome());

		/** @var ISearchBinaryOperator $operator */
		$operator = $receivedQuery->getSearchOperation();
		$this->assertInstanceOf(ISearchBinaryOperator::class, $operator);
		$this->assertEquals(ISearchBinaryOperator::OPERATOR_AND, $operator->getType());
		$this->assertEmpty($operator->getArguments());
	}

	public function testSearchLimitOwnerNested() {
		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->willReturn($this->davFolder);

		/** @var ISearchQuery|null $receivedQuery */
		$receivedQuery = null;
		$this->searchFolder
			->method('search')
			->willReturnCallback(function ($query) use (&$receivedQuery) {
				$receivedQuery = $query;
				return [
					new \OC\Files\Node\Folder($this->rootFolder, $this->view, '/test/path')
				];
			});

		$query = $this->getBasicQuery(\SearchDAV\Query\Operator::OPERATION_EQUAL, FilesPlugin::OWNER_ID_PROPERTYNAME, $this->user->getUID());
		$query->where = new \SearchDAV\Query\Operator(
			\SearchDAV\Query\Operator::OPERATION_AND,
			[
				new \SearchDAV\Query\Operator(
					\SearchDAV\Query\Operator::OPERATION_EQUAL,
					[new SearchPropertyDefinition('{DAV:}getcontenttype', true, true, true), new \SearchDAV\Query\Literal('image/png')]
				),
				new \SearchDAV\Query\Operator(
					\SearchDAV\Query\Operator::OPERATION_EQUAL,
					[new SearchPropertyDefinition(FilesPlugin::OWNER_ID_PROPERTYNAME, true, true, true), new \SearchDAV\Query\Literal($this->user->getUID())]
				)
			]
		);
		$this->search->search($query);

		$this->assertNotNull($receivedQuery);
		$this->assertTrue($receivedQuery->limitToHome());

		/** @var ISearchBinaryOperator $operator */
		$operator = $receivedQuery->getSearchOperation();
		$this->assertInstanceOf(ISearchBinaryOperator::class, $operator);
		$this->assertEquals(ISearchBinaryOperator::OPERATOR_AND, $operator->getType());
		$this->assertCount(2, $operator->getArguments());

		/** @var ISearchBinaryOperator $operator */
		$operator = $operator->getArguments()[1];
		$this->assertInstanceOf(ISearchBinaryOperator::class, $operator);
		$this->assertEquals(ISearchBinaryOperator::OPERATOR_AND, $operator->getType());
		$this->assertEmpty($operator->getArguments());
	}
}
