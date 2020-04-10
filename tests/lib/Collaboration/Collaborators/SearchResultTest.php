<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling
 *
 * @author Joas Schilling <coding@schilljs.com>
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
use OCP\Collaboration\Collaborators\SearchResultType;
use OCP\IContainer;
use Test\TestCase;

class SearchResultTest extends TestCase {
	/** @var  IContainer|\PHPUnit_Framework_MockObject_MockObject */
	protected $container;
	/** @var  ISearch */
	protected $search;

	protected function setUp(): void {
		parent::setUp();

		$this->container = $this->createMock(IContainer::class);

		$this->search = new Search($this->container);
	}

	public function dataAddResultSet() {
		return [
			[[], ['exact' => []]],
			[['users' => ['exact' => null, 'loose' => []]], ['exact' => ['users' => []], 'users' => []]],
			[['groups' => ['exact' => null, 'loose' => ['l1']]], ['exact' => ['groups' => []], 'groups' => ['l1']]],
			[['users' => ['exact' => ['e1'], 'loose' => []]], ['exact' => ['users' => ['e1']], 'users' => []]],
		];
	}

	/**
	 * @dataProvider dataAddResultSet
	 * @param array $toAdd
	 * @param array $expected
	 */
	public function testAddResultSet(array $toAdd, array $expected) {
		$result = new SearchResult();

		foreach ($toAdd as $type => $results) {
			$result->addResultSet(new SearchResultType($type), $results['loose'], $results['exact']);
		}

		$this->assertEquals($expected, $result->asArray());
	}

	public function dataHasResult() {
		$result = ['value' => ['shareWith' => 'l1']];
		return [
			[[],'users', 'n1', false],
			[['users' => ['exact' => null,      'loose' => [$result]]], 'users',  'l1', true],
			[['users' => ['exact' => null,      'loose' => [$result]]], 'users',  'l2', false],
			[['users' => ['exact' => null,      'loose' => [$result]]], 'groups', 'l1', false],
			[['users' => ['exact' => [$result], 'loose' => []]],        'users',  'l1', true],
			[['users' => ['exact' => [$result], 'loose' => []]],        'users',  'l2', false],
			[['users' => ['exact' => [$result], 'loose' => []]],        'groups', 'l1', false],

		];
	}

	/**
	 * @dataProvider dataHasResult
	 * @param array $toAdd
	 * @param string $type
	 * @param string $id
	 * @param bool $expected
	 */
	public function testHasResult(array $toAdd, $type, $id, $expected) {
		$result = new SearchResult();

		foreach ($toAdd as $addType => $results) {
			$result->addResultSet(new SearchResultType($addType), $results['loose'], $results['exact']);
		}

		$this->assertSame($expected, $result->hasResult(new SearchResultType($type), $id));
	}

}
