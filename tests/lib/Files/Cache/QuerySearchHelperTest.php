<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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

namespace Test\Files\Cache;

use OC\DB\QueryBuilder\Literal;
use OC\Files\Cache\QuerySearchHelper;
use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchComparison;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\Files\Search\ISearchOperator;
use Test\TestCase;

/**
 * @group DB
 */
class QuerySearchHelperTest extends TestCase {
	/** @var  IQueryBuilder */
	private $builder;

	/** @var  IMimeTypeLoader|\PHPUnit_Framework_MockObject_MockObject */
	private $mimetypeLoader;

	/** @var  QuerySearchHelper */
	private $querySearchHelper;

	/** @var  integer */
	private $numericStorageId;

	protected function setUp(): void {
		parent::setUp();
		$this->builder = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$this->mimetypeLoader = $this->createMock(IMimeTypeLoader::class);

		$this->mimetypeLoader->expects($this->any())
			->method('getId')
			->willReturnMap([
				['text', 1],
				['text/plain', 2],
				['text/xml', 3],
				['image/jpg', 4],
				['image/png', 5],
				['image', 6],
			]);

		$this->mimetypeLoader->expects($this->any())
			->method('getMimetypeById')
			->willReturnMap([
				[1, 'text'],
				[2, 'text/plain'],
				[3, 'text/xml'],
				[4, 'image/jpg'],
				[5, 'image/png'],
				[6, 'image']
			]);

		$this->querySearchHelper = new QuerySearchHelper($this->mimetypeLoader);
		$this->numericStorageId = 10000;

		$this->builder->select(['fileid'])
			->from('filecache')
			->where($this->builder->expr()->eq('storage', new Literal($this->numericStorageId)));
	}

	protected function tearDown(): void {
		parent::tearDown();

		$builder = \OC::$server->getDatabaseConnection()->getQueryBuilder();

		$builder->delete('filecache')
			->where($builder->expr()->eq('storage', $builder->createNamedParameter($this->numericStorageId, IQueryBuilder::PARAM_INT)));

		$builder->execute();
	}

	private function addCacheEntry(array $data) {
		$data['storage'] = $this->numericStorageId;
		$data['etag'] = 'unimportant';
		$data['storage_mtime'] = $data['mtime'];
		if (!isset($data['path'])) {
			$data['path'] = 'random/' . $this->getUniqueID();
		}
		$data['path_hash'] = md5($data['path']);
		if (!isset($data['mtime'])) {
			$data['mtime'] = 100;
		}
		if (!isset($data['size'])) {
			$data['size'] = 100;
		}
		$data['name'] = basename($data['path']);
		$data['parent'] = -1;
		if (isset($data['mimetype'])) {
			list($mimepart,) = explode('/', $data['mimetype']);
			$data['mimepart'] = $this->mimetypeLoader->getId($mimepart);
			$data['mimetype'] = $this->mimetypeLoader->getId($data['mimetype']);
		} else {
			$data['mimepart'] = 1;
			$data['mimetype'] = 1;
		}

		$builder = \OC::$server->getDatabaseConnection()->getQueryBuilder();

		$values = [];
		foreach ($data as $key => $value) {
			$values[$key] = $builder->createNamedParameter($value);
		}

		$builder->insert('filecache')
			->values($values)
			->execute();

		return $builder->getLastInsertId();
	}

	private function search(ISearchOperator $operator) {
		$dbOperator = $this->querySearchHelper->searchOperatorToDBExpr($this->builder, $operator);
		$this->builder->andWhere($dbOperator);
		return $this->builder->execute()->fetchAll(\PDO::FETCH_COLUMN);
	}

	public function comparisonProvider() {
		return [
			[new SearchComparison(ISearchComparison::COMPARE_GREATER_THAN, 'mtime', 125), [1]],
			[new SearchComparison(ISearchComparison::COMPARE_LESS_THAN, 'mtime', 125), [0]],
			[new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'size', 125), []],
			[new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'size', 50), [0, 1]],
			[new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'name', 'foobar'), [0]],
			[new SearchComparison(ISearchComparison::COMPARE_LIKE, 'name', 'foo%'), [0, 1]],
			[new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'mimetype', 'image/jpg'), [0]],
			[new SearchComparison(ISearchComparison::COMPARE_LIKE, 'mimetype', 'image/%'), [0, 1]],
			[new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [
				new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'size', 50),
				new SearchComparison(ISearchComparison::COMPARE_LESS_THAN, 'mtime', 125)
			]), [0]],
			[new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [
				new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'size', 50),
				new SearchComparison(ISearchComparison::COMPARE_LESS_THAN, 'mtime', 125),
				new SearchComparison(ISearchComparison::COMPARE_LIKE, 'mimetype', 'text/%')
			]), []],
			[new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_OR, [
				new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'mtime', 100),
				new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'mtime', 150),
			]), [0, 1]],
			[new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_NOT, [
				new SearchComparison(ISearchComparison::COMPARE_EQUAL, 'mtime', 150),
			]), [0]],
			[new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_NOT, [
				new SearchComparison(ISearchComparison::COMPARE_GREATER_THAN, 'mtime', 125),
			]), [0]],
			[new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_NOT, [
				new SearchComparison(ISearchComparison::COMPARE_LESS_THAN, 'mtime', 125),
			]), [1]],
			[new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_NOT, [
				new SearchComparison(ISearchComparison::COMPARE_LIKE, 'name', '%bar'),
			]), [1]],

		];
	}

	/**
	 * @dataProvider comparisonProvider
	 *
	 * @param ISearchOperator $operator
	 * @param array $fileIds
	 */
	public function testComparison(ISearchOperator $operator, array $fileIds) {
		$fileId = [];
		$fileId[] = $this->addCacheEntry([
			'path' => 'foobar',
			'mtime' => 100,
			'size' => 50,
			'mimetype' => 'image/jpg'
		]);

		$fileId[] = $this->addCacheEntry([
			'path' => 'fooasd',
			'mtime' => 150,
			'size' => 50,
			'mimetype' => 'image/png'
		]);

		$fileIds = array_map(function ($i) use ($fileId) {
			return $fileId[$i];
		}, $fileIds);

		$results = $this->search($operator);

		sort($fileIds);
		sort($results);

		$this->assertEquals($fileIds, $results);
	}
}
