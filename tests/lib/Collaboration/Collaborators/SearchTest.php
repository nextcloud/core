<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace Test\Collaboration\Collaborators;

use OC\Collaboration\Collaborators\Search;
use OC\Collaboration\Collaborators\SearchResult;
use OCP\Collaboration\Collaborators\ISearch;
use OCP\Collaboration\Collaborators\ISearchPlugin;
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IContainer;
use OCP\Share;
use Test\TestCase;

class SearchTest extends TestCase {
	/** @var  IContainer|\PHPUnit_Framework_MockObject_MockObject */
	protected $container;
	/** @var  ISearch */
	protected $search;

	protected function setUp(): void {
		parent::setUp();

		$this->container = $this->createMock(IContainer::class);

		$this->search = new Search($this->container);
	}

	/**
	 * @dataProvider dataSearchSharees
	 *
	 * @param string $searchTerm
	 * @param array $shareTypes
	 * @param int $page
	 * @param int $perPage
	 * @param array $mockedUserResult
	 * @param array $mockedGroupsResult
	 * @param array $mockedRemotesResult
	 * @param array $expected
	 * @param bool $expectedMoreResults
	 */
	public function testSearch(
		$searchTerm,
		array $shareTypes,
		$page,
		$perPage,
		array $mockedUserResult,
		array $mockedGroupsResult,
		array $mockedRemotesResult,
		array $expected,
		$expectedMoreResults
	) {
		$searchResult = new SearchResult();

		$userPlugin = $this->createMock(ISearchPlugin::class);
		$userPlugin->expects($this->any())
			->method('search')
			->willReturnCallback(function () use ($searchResult, $mockedUserResult, $expectedMoreResults) {
				$type = new SearchResultType('users');
				$searchResult->addResultSet($type, $mockedUserResult);
				return $expectedMoreResults;
			});

		$groupPlugin = $this->createMock(ISearchPlugin::class);
		$groupPlugin->expects($this->any())
			->method('search')
			->willReturnCallback(function () use ($searchResult, $mockedGroupsResult, $expectedMoreResults) {
				$type = new SearchResultType('groups');
				$searchResult->addResultSet($type, $mockedGroupsResult);
				return $expectedMoreResults;
			});

		$remotePlugin = $this->createMock(ISearchPlugin::class);
		$remotePlugin->expects($this->any())
			->method('search')
			->willReturnCallback(function () use ($searchResult, $mockedRemotesResult, $expectedMoreResults) {
				if($mockedRemotesResult !== null) {
					$type = new SearchResultType('remotes');
					$searchResult->addResultSet($type, $mockedRemotesResult['results'], $mockedRemotesResult['exact']);
					if($mockedRemotesResult['exactIdMatch'] === true) {
						$searchResult->markExactIdMatch($type);
					}
				}
				return $expectedMoreResults;
			});

		$this->container->expects($this->any())
			->method('resolve')
			->willReturnCallback(function ($class) use ($searchResult, $userPlugin, $groupPlugin, $remotePlugin) {
				if($class === SearchResult::class) {
					return $searchResult;
				} elseif ($class === $userPlugin) {
					return $userPlugin;
				} elseif ($class === $groupPlugin) {
					return $groupPlugin;
				} elseif ($class === $remotePlugin) {
					return $remotePlugin;
				}
				return null;
			});

		$this->search->registerPlugin(['shareType' => 'SHARE_TYPE_USER', 'class' => $userPlugin]);
		$this->search->registerPlugin(['shareType' => 'SHARE_TYPE_GROUP', 'class' => $groupPlugin]);
		$this->search->registerPlugin(['shareType' => 'SHARE_TYPE_REMOTE', 'class' => $remotePlugin]);

		list($results, $moreResults) = $this->search->search($searchTerm, $shareTypes, false, $perPage, $perPage * ($page - 1));

		$this->assertEquals($expected, $results);
		$this->assertSame($expectedMoreResults, $moreResults);
	}

	public function dataSearchSharees() {
		return [
			[
				'test', [Share::SHARE_TYPE_USER, Share::SHARE_TYPE_GROUP, Share::SHARE_TYPE_REMOTE], 1, 2, [], [], ['results' => [], 'exact' => [], 'exactIdMatch' => false],
				[
					'exact' => ['users' => [], 'groups' => [], 'remotes' => []],
					'users' => [],
					'groups' => [],
					'remotes' => [],
				], false
			],
			[
				'test', [Share::SHARE_TYPE_USER, Share::SHARE_TYPE_GROUP, Share::SHARE_TYPE_REMOTE], 1, 2, [], [], ['results' => [], 'exact' => [], 'exactIdMatch' => false],
				[
					'exact' => ['users' => [], 'groups' => [], 'remotes' => []],
					'users' => [],
					'groups' => [],
					'remotes' => [],
				], false
			],
			[
				'test', [Share::SHARE_TYPE_USER, Share::SHARE_TYPE_GROUP, Share::SHARE_TYPE_REMOTE], 1, 2, [
					['label' => 'test One', 'value' => ['shareType' => Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				], [
					['label' => 'testgroup1', 'value' => ['shareType' => Share::SHARE_TYPE_GROUP, 'shareWith' => 'testgroup1']],
				], [
					'results' => [['label' => 'testz@remote', 'value' => ['shareType' => Share::SHARE_TYPE_REMOTE, 'shareWith' => 'testz@remote']]], 'exact' => [], 'exactIdMatch' => false,
				],
				[
					'exact' => ['users' => [], 'groups' => [], 'remotes' => []],
					'users' => [
						['label' => 'test One', 'value' => ['shareType' => Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					],
					'groups' => [
						['label' => 'testgroup1', 'value' => ['shareType' => Share::SHARE_TYPE_GROUP, 'shareWith' => 'testgroup1']],
					],
					'remotes' => [
						['label' => 'testz@remote', 'value' => ['shareType' => Share::SHARE_TYPE_REMOTE, 'shareWith' => 'testz@remote']],
					],
				], true,
			],
			// No groups requested
			[
				'test', [Share::SHARE_TYPE_USER, Share::SHARE_TYPE_REMOTE], 1, 2, [
					['label' => 'test One', 'value' => ['shareType' => Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				], [], [
					'results' => [['label' => 'testz@remote', 'value' => ['shareType' => Share::SHARE_TYPE_REMOTE, 'shareWith' => 'testz@remote']]], 'exact' => [], 'exactIdMatch' => false
				],
				[
					'exact' => ['users' => [], 'remotes' => []],
					'users' => [
						['label' => 'test One', 'value' => ['shareType' => Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					],
					'remotes' => [
						['label' => 'testz@remote', 'value' => ['shareType' => Share::SHARE_TYPE_REMOTE, 'shareWith' => 'testz@remote']],
					],
				], false,
			],
			// Share type restricted to user - Only one user
			[
				'test', [Share::SHARE_TYPE_USER], 1, 2, [
					['label' => 'test One', 'value' => ['shareType' => Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
				], [], [],
				[
					'exact' => ['users' => []],
					'users' => [
						['label' => 'test One', 'value' => ['shareType' => Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					],
				], false,
			],
			// Share type restricted to user - Multipage result
			[
				'test', [Share::SHARE_TYPE_USER], 1, 2, [
					['label' => 'test 1', 'value' => ['shareType' => Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
					['label' => 'test 2', 'value' => ['shareType' => Share::SHARE_TYPE_USER, 'shareWith' => 'test2']],
				], [], [],
				[
					'exact' => ['users' => []],
					'users' => [
						['label' => 'test 1', 'value' => ['shareType' => Share::SHARE_TYPE_USER, 'shareWith' => 'test1']],
						['label' => 'test 2', 'value' => ['shareType' => Share::SHARE_TYPE_USER, 'shareWith' => 'test2']],
					],
				], true,
			],
		];
	}
}
