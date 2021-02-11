<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\Tests\unit\SystemTag;

use OC\SystemTag\SystemTag;
use OCA\DAV\SystemTag\SystemTagNode;
use OCA\DAV\SystemTag\SystemTagsByIdCollection;
use OCA\DAV\SystemTag\SystemTagsObjectMappingCollection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagAlreadyExistsException;
use Sabre\DAV\Tree;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class SystemTagPluginTest extends \Test\TestCase {
	public const ID_PROPERTYNAME = \OCA\DAV\SystemTag\SystemTagPlugin::ID_PROPERTYNAME;
	public const DISPLAYNAME_PROPERTYNAME = \OCA\DAV\SystemTag\SystemTagPlugin::DISPLAYNAME_PROPERTYNAME;
	public const USERVISIBLE_PROPERTYNAME = \OCA\DAV\SystemTag\SystemTagPlugin::USERVISIBLE_PROPERTYNAME;
	public const USERASSIGNABLE_PROPERTYNAME = \OCA\DAV\SystemTag\SystemTagPlugin::USERASSIGNABLE_PROPERTYNAME;
	public const CANASSIGN_PROPERTYNAME = \OCA\DAV\SystemTag\SystemTagPlugin::CANASSIGN_PROPERTYNAME;
	public const GROUPS_PROPERTYNAME = \OCA\DAV\SystemTag\SystemTagPlugin::GROUPS_PROPERTYNAME;

	/**
	 * @var \Sabre\DAV\Server
	 */
	private $server;

	/**
	 * @var \Sabre\DAV\Tree
	 */
	private $tree;

	/**
	 * @var \OCP\SystemTag\ISystemTagManager
	 */
	private $tagManager;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * @var IUserSession
	 */
	private $userSession;

	/**
	 * @var IUser
	 */
	private $user;

	/**
	 * @var \OCA\DAV\SystemTag\SystemTagPlugin
	 */
	private $plugin;

	protected function setUp(): void {
		parent::setUp();
		$this->tree = $this->getMockBuilder(Tree::class)
			->disableOriginalConstructor()
			->getMock();

		$this->server = new \Sabre\DAV\Server($this->tree);

		$this->tagManager = $this->getMockBuilder(ISystemTagManager::class)
			->getMock();
		$this->groupManager = $this->getMockBuilder(IGroupManager::class)
			->getMock();
		$this->user = $this->getMockBuilder(IUser::class)
			->getMock();
		$this->userSession = $this->getMockBuilder(IUserSession::class)
			->getMock();
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->willReturn($this->user);
		$this->userSession
			->expects($this->any())
			->method('isLoggedIn')
			->willReturn(true);

		$this->plugin = new \OCA\DAV\SystemTag\SystemTagPlugin(
			$this->tagManager,
			$this->groupManager,
			$this->userSession
		);
		$this->plugin->initialize($this->server);
	}

	public function getPropertiesDataProvider() {
		return [
			[
				new SystemTag(1, 'Test', true, true),
				[],
				[
					self::ID_PROPERTYNAME,
					self::DISPLAYNAME_PROPERTYNAME,
					self::USERVISIBLE_PROPERTYNAME,
					self::USERASSIGNABLE_PROPERTYNAME,
					self::CANASSIGN_PROPERTYNAME,
				],
				[
					self::ID_PROPERTYNAME => '1',
					self::DISPLAYNAME_PROPERTYNAME => 'Test',
					self::USERVISIBLE_PROPERTYNAME => 'true',
					self::USERASSIGNABLE_PROPERTYNAME => 'true',
					self::CANASSIGN_PROPERTYNAME => 'true',
				]
			],
			[
				new SystemTag(1, 'Test', true, false),
				[],
				[
					self::ID_PROPERTYNAME,
					self::DISPLAYNAME_PROPERTYNAME,
					self::USERVISIBLE_PROPERTYNAME,
					self::USERASSIGNABLE_PROPERTYNAME,
					self::CANASSIGN_PROPERTYNAME,
				],
				[
					self::ID_PROPERTYNAME => '1',
					self::DISPLAYNAME_PROPERTYNAME => 'Test',
					self::USERVISIBLE_PROPERTYNAME => 'true',
					self::USERASSIGNABLE_PROPERTYNAME => 'false',
					self::CANASSIGN_PROPERTYNAME => 'false',
				]
			],
			[
				new SystemTag(1, 'Test', true, false),
				['group1', 'group2'],
				[
					self::ID_PROPERTYNAME,
					self::GROUPS_PROPERTYNAME,
				],
				[
					self::ID_PROPERTYNAME => '1',
					self::GROUPS_PROPERTYNAME => 'group1|group2',
				]
			],
			[
				new SystemTag(1, 'Test', true, true),
				['group1', 'group2'],
				[
					self::ID_PROPERTYNAME,
					self::GROUPS_PROPERTYNAME,
				],
				[
					self::ID_PROPERTYNAME => '1',
					// groups only returned when userAssignable is false
					self::GROUPS_PROPERTYNAME => '',
				]
			],
		];
	}

	/**
	 * @dataProvider getPropertiesDataProvider
	 */
	public function testGetProperties(ISystemTag $systemTag, $groups, $requestedProperties, $expectedProperties) {
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('admin');
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$node = $this->getMockBuilder(SystemTagNode::class)
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->any())
			->method('getSystemTag')
			->willReturn($systemTag);

		$this->tagManager->expects($this->any())
			->method('canUserAssignTag')
			->willReturn($systemTag->isUserAssignable());

		$this->tagManager->expects($this->any())
			->method('getTagGroups')
			->willReturn($groups);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/systemtag/1')
			->willReturn($node);

		$propFind = new \Sabre\DAV\PropFind(
			'/systemtag/1',
			$requestedProperties,
			0
		);

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);

		$result = $propFind->getResultForMultiStatus();

		$this->assertEmpty($result[404]);
		$this->assertEquals($expectedProperties, $result[200]);
	}

	
	public function testGetPropertiesForbidden() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$systemTag = new SystemTag(1, 'Test', true, false);
		$requestedProperties = [
			self::ID_PROPERTYNAME,
			self::GROUPS_PROPERTYNAME,
		];
		$this->user->expects($this->once())
			->method('getUID')
			->willReturn('admin');
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(false);

		$node = $this->getMockBuilder(SystemTagNode::class)
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->any())
			->method('getSystemTag')
			->willReturn($systemTag);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/systemtag/1')
			->willReturn($node);

		$propFind = new \Sabre\DAV\PropFind(
			'/systemtag/1',
			$requestedProperties,
			0
		);

		$this->plugin->handleGetProperties(
			$propFind,
			$node
		);
	}

	public function testUpdatePropertiesAdmin() {
		$systemTag = new SystemTag(1, 'Test', true, false);
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('admin');
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$node = $this->getMockBuilder(SystemTagNode::class)
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->any())
			->method('getSystemTag')
			->willReturn($systemTag);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/systemtag/1')
			->willReturn($node);

		$node->expects($this->once())
			->method('update')
			->with('Test changed', false, true);

		$this->tagManager->expects($this->once())
			->method('setTagGroups')
			->with($systemTag, ['group1', 'group2']);

		// properties to set
		$propPatch = new \Sabre\DAV\PropPatch([
			self::DISPLAYNAME_PROPERTYNAME => 'Test changed',
			self::USERVISIBLE_PROPERTYNAME => 'false',
			self::USERASSIGNABLE_PROPERTYNAME => 'true',
			self::GROUPS_PROPERTYNAME => 'group1|group2',
		]);

		$this->plugin->handleUpdateProperties(
			'/systemtag/1',
			$propPatch
		);

		$propPatch->commit();

		// all requested properties removed, as they were processed already
		$this->assertEmpty($propPatch->getRemainingMutations());

		$result = $propPatch->getResult();
		$this->assertEquals(200, $result[self::DISPLAYNAME_PROPERTYNAME]);
		$this->assertEquals(200, $result[self::USERASSIGNABLE_PROPERTYNAME]);
		$this->assertEquals(200, $result[self::USERVISIBLE_PROPERTYNAME]);
	}

	
	public function testUpdatePropertiesForbidden() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$systemTag = new SystemTag(1, 'Test', true, false);
		$this->user->expects($this->any())
			->method('getUID')
			->willReturn('admin');
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->with('admin')
			->willReturn(false);

		$node = $this->getMockBuilder(SystemTagNode::class)
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->any())
			->method('getSystemTag')
			->willReturn($systemTag);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/systemtag/1')
			->willReturn($node);

		$node->expects($this->never())
			->method('update');

		$this->tagManager->expects($this->never())
			->method('setTagGroups');

		// properties to set
		$propPatch = new \Sabre\DAV\PropPatch([
			self::GROUPS_PROPERTYNAME => 'group1|group2',
		]);

		$this->plugin->handleUpdateProperties(
			'/systemtag/1',
			$propPatch
		);

		$propPatch->commit();
	}

	public function createTagInsufficientPermissionsProvider() {
		return [
			[true, false, ''],
			[false, true, ''],
			[true, true, 'group1|group2'],
		];
	}
	/**
	 * @dataProvider createTagInsufficientPermissionsProvider
	 */
	public function testCreateNotAssignableTagAsRegularUser($userVisible, $userAssignable, $groups) {
		$this->expectException(\Sabre\DAV\Exception\BadRequest::class);
		$this->expectExceptionMessage('Not sufficient permissions');

		$this->user->expects($this->once())
			->method('getUID')
			->willReturn('admin');
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(false);

		$requestData = [
			'name' => 'Test',
			'userVisible' => $userVisible,
			'userAssignable' => $userAssignable,
		];
		if (!empty($groups)) {
			$requestData['groups'] = $groups;
		}
		$requestData = json_encode($requestData);

		$node = $this->getMockBuilder(SystemTagsByIdCollection::class)
			->disableOriginalConstructor()
			->getMock();
		$this->tagManager->expects($this->never())
			->method('createTag');
		$this->tagManager->expects($this->never())
			->method('setTagGroups');

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/systemtags')
			->willReturn($node);

		$request = $this->getMockBuilder(RequestInterface::class)
			->disableOriginalConstructor()
			->getMock();
		$response = $this->getMockBuilder(ResponseInterface::class)
			->disableOriginalConstructor()
			->getMock();

		$request->expects($this->once())
			->method('getPath')
			->willReturn('/systemtags');

		$request->expects($this->once())
			->method('getBodyAsString')
			->willReturn($requestData);

		$request->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->willReturn('application/json');

		$this->plugin->httpPost($request, $response);
	}

	public function testCreateTagInByIdCollectionAsRegularUser() {
		$systemTag = new SystemTag(1, 'Test', true, false);

		$requestData = json_encode([
			'name' => 'Test',
			'userVisible' => true,
			'userAssignable' => true,
		]);

		$node = $this->getMockBuilder(SystemTagsByIdCollection::class)
			->disableOriginalConstructor()
			->getMock();
		$this->tagManager->expects($this->once())
			->method('createTag')
			->with('Test', true, true)
			->willReturn($systemTag);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/systemtags')
			->willReturn($node);

		$request = $this->getMockBuilder(RequestInterface::class)
			->disableOriginalConstructor()
			->getMock();
		$response = $this->getMockBuilder(ResponseInterface::class)
			->disableOriginalConstructor()
			->getMock();

		$request->expects($this->once())
			->method('getPath')
			->willReturn('/systemtags');

		$request->expects($this->once())
			->method('getBodyAsString')
			->willReturn($requestData);

		$request->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->willReturn('application/json');

		$request->expects($this->once())
			->method('getUrl')
			->willReturn('http://example.com/dav/systemtags');

		$response->expects($this->once())
			->method('setHeader')
			->with('Content-Location', 'http://example.com/dav/systemtags/1');

		$this->plugin->httpPost($request, $response);
	}

	public function createTagProvider() {
		return [
			[true, false, ''],
			[false, false, ''],
			[true, false, 'group1|group2'],
		];
	}

	/**
	 * @dataProvider createTagProvider
	 */
	public function testCreateTagInByIdCollection($userVisible, $userAssignable, $groups) {
		$this->user->expects($this->once())
			->method('getUID')
			->willReturn('admin');
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$systemTag = new SystemTag(1, 'Test', true, false);

		$requestData = [
			'name' => 'Test',
			'userVisible' => $userVisible,
			'userAssignable' => $userAssignable,
		];
		if (!empty($groups)) {
			$requestData['groups'] = $groups;
		}
		$requestData = json_encode($requestData);

		$node = $this->getMockBuilder(SystemTagsByIdCollection::class)
			->disableOriginalConstructor()
			->getMock();
		$this->tagManager->expects($this->once())
			->method('createTag')
			->with('Test', $userVisible, $userAssignable)
			->willReturn($systemTag);
		
		if (!empty($groups)) {
			$this->tagManager->expects($this->once())
				->method('setTagGroups')
				->with($systemTag, explode('|', $groups))
				->willReturn($systemTag);
		} else {
			$this->tagManager->expects($this->never())
				->method('setTagGroups');
		}

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/systemtags')
			->willReturn($node);

		$request = $this->getMockBuilder(RequestInterface::class)
				->disableOriginalConstructor()
				->getMock();
		$response = $this->getMockBuilder(ResponseInterface::class)
				->disableOriginalConstructor()
				->getMock();

		$request->expects($this->once())
			->method('getPath')
			->willReturn('/systemtags');

		$request->expects($this->once())
			->method('getBodyAsString')
			->willReturn($requestData);

		$request->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->willReturn('application/json');

		$request->expects($this->once())
			->method('getUrl')
			->willReturn('http://example.com/dav/systemtags');

		$response->expects($this->once())
			->method('setHeader')
			->with('Content-Location', 'http://example.com/dav/systemtags/1');

		$this->plugin->httpPost($request, $response);
	}

	public function nodeClassProvider() {
		return [
			['\OCA\DAV\SystemTag\SystemTagsByIdCollection'],
			['\OCA\DAV\SystemTag\SystemTagsObjectMappingCollection'],
		];
	}

	public function testCreateTagInMappingCollection() {
		$this->user->expects($this->once())
			->method('getUID')
			->willReturn('admin');
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$systemTag = new SystemTag(1, 'Test', true, false);

		$requestData = json_encode([
			'name' => 'Test',
			'userVisible' => true,
			'userAssignable' => false,
		]);

		$node = $this->getMockBuilder(SystemTagsObjectMappingCollection::class)
			->disableOriginalConstructor()
			->getMock();

		$this->tagManager->expects($this->once())
			->method('createTag')
			->with('Test', true, false)
			->willReturn($systemTag);

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/systemtags-relations/files/12')
			->willReturn($node);

		$node->expects($this->once())
			->method('createFile')
			->with(1);

		$request = $this->getMockBuilder(RequestInterface::class)
				->disableOriginalConstructor()
				->getMock();
		$response = $this->getMockBuilder(ResponseInterface::class)
				->disableOriginalConstructor()
				->getMock();

		$request->expects($this->once())
			->method('getPath')
			->willReturn('/systemtags-relations/files/12');

		$request->expects($this->once())
			->method('getBodyAsString')
			->willReturn($requestData);

		$request->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->willReturn('application/json');

		$request->expects($this->once())
			->method('getBaseUrl')
			->willReturn('http://example.com/dav/');

		$response->expects($this->once())
			->method('setHeader')
			->with('Content-Location', 'http://example.com/dav/systemtags/1');

		$this->plugin->httpPost($request, $response);
	}

	
	public function testCreateTagToUnknownNode() {
		$this->expectException(\Sabre\DAV\Exception\NotFound::class);

		$node = $this->getMockBuilder(SystemTagsObjectMappingCollection::class)
			->disableOriginalConstructor()
			->getMock();

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->will($this->throwException(new \Sabre\DAV\Exception\NotFound()));

		$this->tagManager->expects($this->never())
			->method('createTag');

		$node->expects($this->never())
			->method('createFile');

		$request = $this->getMockBuilder(RequestInterface::class)
				->disableOriginalConstructor()
				->getMock();
		$response = $this->getMockBuilder(ResponseInterface::class)
				->disableOriginalConstructor()
				->getMock();

		$request->expects($this->once())
			->method('getPath')
			->willReturn('/systemtags-relations/files/12');

		$this->plugin->httpPost($request, $response);
	}

	/**
	 * @dataProvider nodeClassProvider
	 */
	public function testCreateTagConflict($nodeClass) {
		$this->expectException(\Sabre\DAV\Exception\Conflict::class);

		$this->user->expects($this->once())
			->method('getUID')
			->willReturn('admin');
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$requestData = json_encode([
			'name' => 'Test',
			'userVisible' => true,
			'userAssignable' => false,
		]);

		$node = $this->getMockBuilder($nodeClass)
			->disableOriginalConstructor()
			->getMock();
		$this->tagManager->expects($this->once())
			->method('createTag')
			->with('Test', true, false)
			->will($this->throwException(new TagAlreadyExistsException('Tag already exists')));

		$this->tree->expects($this->any())
			->method('getNodeForPath')
			->with('/systemtags')
			->willReturn($node);

		$request = $this->getMockBuilder(RequestInterface::class)
				->disableOriginalConstructor()
				->getMock();
		$response = $this->getMockBuilder(ResponseInterface::class)
				->disableOriginalConstructor()
				->getMock();

		$request->expects($this->once())
			->method('getPath')
			->willReturn('/systemtags');

		$request->expects($this->once())
			->method('getBodyAsString')
			->willReturn($requestData);

		$request->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->willReturn('application/json');

		$this->plugin->httpPost($request, $response);
	}
}
