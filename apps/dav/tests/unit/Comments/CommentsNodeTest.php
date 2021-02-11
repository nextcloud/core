<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Tests\unit\Comments;

use OCA\DAV\Comments\CommentNode;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\MessageTooLongException;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Sabre\DAV\PropPatch;

class CommentsNodeTest extends \Test\TestCase {

	/** @var  ICommentsManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $commentsManager;

	protected $comment;
	protected $node;
	protected $userManager;
	protected $logger;
	protected $userSession;

	protected function setUp(): void {
		parent::setUp();

		$this->commentsManager = $this->getMockBuilder(ICommentsManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->comment = $this->getMockBuilder(IComment::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession = $this->getMockBuilder(IUserSession::class)
			->disableOriginalConstructor()
			->getMock();
		$this->logger = $this->getMockBuilder(ILogger::class)
			->disableOriginalConstructor()
			->getMock();

		$this->node = new CommentNode(
			$this->commentsManager,
			$this->comment,
			$this->userManager,
			$this->userSession,
			$this->logger
		);
	}

	public function testDelete() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();

		$user->expects($this->once())
			->method('getUID')
			->willReturn('alice');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->comment->expects($this->once())
			->method('getId')
			->willReturn('19');

		$this->comment->expects($this->any())
			->method('getActorType')
			->willReturn('users');

		$this->comment->expects($this->any())
			->method('getActorId')
			->willReturn('alice');

		$this->commentsManager->expects($this->once())
			->method('delete')
			->with('19');

		$this->node->delete();
	}


	public function testDeleteForbidden() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();

		$user->expects($this->once())
			->method('getUID')
			->willReturn('mallory');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->comment->expects($this->never())
			->method('getId');

		$this->comment->expects($this->any())
			->method('getActorType')
			->willReturn('users');

		$this->comment->expects($this->any())
			->method('getActorId')
			->willReturn('alice');

		$this->commentsManager->expects($this->never())
			->method('delete');

		$this->node->delete();
	}

	public function testGetName() {
		$id = '19';
		$this->comment->expects($this->once())
			->method('getId')
			->willReturn($id);

		$this->assertSame($this->node->getName(), $id);
	}


	public function testSetName() {
		$this->expectException(\Sabre\DAV\Exception\MethodNotAllowed::class);

		$this->node->setName('666');
	}

	public function testGetLastModified() {
		$this->assertSame($this->node->getLastModified(), null);
	}

	public function testUpdateComment() {
		$msg = 'Hello Earth';

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();

		$user->expects($this->once())
			->method('getUID')
			->willReturn('alice');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->comment->expects($this->once())
			->method('setMessage')
			->with($msg);

		$this->comment->expects($this->any())
			->method('getActorType')
			->willReturn('users');

		$this->comment->expects($this->any())
			->method('getActorId')
			->willReturn('alice');

		$this->commentsManager->expects($this->once())
			->method('save')
			->with($this->comment);

		$this->assertTrue($this->node->updateComment($msg));
	}


	public function testUpdateCommentLogException() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('buh!');

		$msg = null;

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();

		$user->expects($this->once())
			->method('getUID')
			->willReturn('alice');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->comment->expects($this->once())
			->method('setMessage')
			->with($msg)
			->will($this->throwException(new \Exception('buh!')));

		$this->comment->expects($this->any())
			->method('getActorType')
			->willReturn('users');

		$this->comment->expects($this->any())
			->method('getActorId')
			->willReturn('alice');

		$this->commentsManager->expects($this->never())
			->method('save');

		$this->logger->expects($this->once())
			->method('logException');

		$this->node->updateComment($msg);
	}


	public function testUpdateCommentMessageTooLongException() {
		$this->expectException(\Sabre\DAV\Exception\BadRequest::class);
		$this->expectExceptionMessage('Message exceeds allowed character limit of');

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();

		$user->expects($this->once())
			->method('getUID')
			->willReturn('alice');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->comment->expects($this->once())
			->method('setMessage')
			->will($this->throwException(new MessageTooLongException()));

		$this->comment->expects($this->any())
			->method('getActorType')
			->willReturn('users');

		$this->comment->expects($this->any())
			->method('getActorId')
			->willReturn('alice');

		$this->commentsManager->expects($this->never())
			->method('save');

		$this->logger->expects($this->once())
			->method('logException');

		// imagine 'foo' has >1k characters. comment is mocked anyway.
		$this->node->updateComment('foo');
	}


	public function testUpdateForbiddenByUser() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$msg = 'HaXX0r';

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();

		$user->expects($this->once())
			->method('getUID')
			->willReturn('mallory');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->comment->expects($this->never())
			->method('setMessage');

		$this->comment->expects($this->any())
			->method('getActorType')
			->willReturn('users');

		$this->comment->expects($this->any())
			->method('getActorId')
			->willReturn('alice');

		$this->commentsManager->expects($this->never())
			->method('save');

		$this->node->updateComment($msg);
	}


	public function testUpdateForbiddenByType() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$msg = 'HaXX0r';

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();

		$user->expects($this->never())
			->method('getUID');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->comment->expects($this->never())
			->method('setMessage');

		$this->comment->expects($this->any())
			->method('getActorType')
			->willReturn('bots');

		$this->commentsManager->expects($this->never())
			->method('save');

		$this->node->updateComment($msg);
	}


	public function testUpdateForbiddenByNotLoggedIn() {
		$this->expectException(\Sabre\DAV\Exception\Forbidden::class);

		$msg = 'HaXX0r';

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$this->comment->expects($this->never())
			->method('setMessage');

		$this->comment->expects($this->any())
			->method('getActorType')
			->willReturn('users');

		$this->commentsManager->expects($this->never())
			->method('save');

		$this->node->updateComment($msg);
	}

	public function testPropPatch() {
		$propPatch = $this->getMockBuilder(PropPatch::class)
			->disableOriginalConstructor()
			->getMock();

		$propPatch->expects($this->once())
			->method('handle')
			->with('{http://owncloud.org/ns}message');

		$this->node->propPatch($propPatch);
	}

	public function testGetProperties() {
		$ns = '{http://owncloud.org/ns}';
		$expected = [
			$ns . 'id' => '123',
			$ns . 'parentId' => '12',
			$ns . 'topmostParentId' => '2',
			$ns . 'childrenCount' => 3,
			$ns . 'message' => 'such a nice file you have…',
			$ns . 'mentions' => [
				[ $ns . 'mention' => [
					$ns . 'mentionType' => 'user',
					$ns . 'mentionId' => 'alice',
					$ns . 'mentionDisplayName' => 'Alice Al-Isson',
				] ],
				[ $ns . 'mention' => [
					$ns . 'mentionType' => 'user',
					$ns . 'mentionId' => 'bob',
					$ns . 'mentionDisplayName' => 'Unknown user',
				] ],
			],
			$ns . 'verb' => 'comment',
			$ns . 'actorType' => 'users',
			$ns . 'actorId' => 'alice',
			$ns . 'actorDisplayName' => 'Alice of Wonderland',
			$ns . 'creationDateTime' => new \DateTime('2016-01-10 18:48:00'),
			$ns . 'latestChildDateTime' => new \DateTime('2016-01-12 18:48:00'),
			$ns . 'objectType' => 'files',
			$ns . 'objectId' => '1848',
			$ns . 'referenceId' => 'ref',
			$ns . 'isUnread' => null,
		];

		$this->commentsManager->expects($this->exactly(2))
			->method('resolveDisplayName')
			->withConsecutive(
				[$this->equalTo('user'), $this->equalTo('alice')],
				[$this->equalTo('user'), $this->equalTo('bob')]
			)
			->willReturnOnConsecutiveCalls('Alice Al-Isson', 'Unknown user');

		$this->comment->expects($this->once())
			->method('getId')
			->willReturn($expected[$ns . 'id']);

		$this->comment->expects($this->once())
			->method('getParentId')
			->willReturn($expected[$ns . 'parentId']);

		$this->comment->expects($this->once())
			->method('getTopmostParentId')
			->willReturn($expected[$ns . 'topmostParentId']);

		$this->comment->expects($this->once())
			->method('getChildrenCount')
			->willReturn($expected[$ns . 'childrenCount']);

		$this->comment->expects($this->once())
			->method('getMessage')
			->willReturn($expected[$ns . 'message']);

		$this->comment->expects($this->once())
			->method('getMentions')
			->willReturn([
				['type' => 'user', 'id' => 'alice'],
				['type' => 'user', 'id' => 'bob'],
			]);

		$this->comment->expects($this->once())
			->method('getVerb')
			->willReturn($expected[$ns . 'verb']);

		$this->comment->expects($this->exactly(2))
			->method('getActorType')
			->willReturn($expected[$ns . 'actorType']);

		$this->comment->expects($this->exactly(2))
			->method('getActorId')
			->willReturn($expected[$ns . 'actorId']);

		$this->comment->expects($this->once())
			->method('getCreationDateTime')
			->willReturn($expected[$ns . 'creationDateTime']);

		$this->comment->expects($this->once())
			->method('getLatestChildDateTime')
			->willReturn($expected[$ns . 'latestChildDateTime']);

		$this->comment->expects($this->once())
			->method('getObjectType')
			->willReturn($expected[$ns . 'objectType']);

		$this->comment->expects($this->once())
			->method('getObjectId')
			->willReturn($expected[$ns . 'objectId']);

		$this->comment->expects($this->once())
			->method('getReferenceId')
			->willReturn($expected[$ns . 'referenceId']);

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->expects($this->once())
			->method('getDisplayName')
			->willReturn($expected[$ns . 'actorDisplayName']);

		$this->userManager->expects($this->once())
			->method('get')
			->with('alice')
			->willReturn($user);

		$properties = $this->node->getProperties(null);

		foreach ($properties as $name => $value) {
			$this->assertArrayHasKey($name, $expected);
			$this->assertSame($expected[$name], $value);
			unset($expected[$name]);
		}
		$this->assertTrue(empty($expected));
	}

	public function readCommentProvider() {
		$creationDT = new \DateTime('2016-01-19 18:48:00');
		$diff = new \DateInterval('PT2H');
		$readDT1 = clone $creationDT;
		$readDT1->sub($diff);
		$readDT2 = clone $creationDT;
		$readDT2->add($diff);
		return [
			[$creationDT, $readDT1, 'true'],
			[$creationDT, $readDT2, 'false'],
			[$creationDT, null, 'true'],
		];
	}

	/**
	 * @dataProvider readCommentProvider
	 * @param $expected
	 */
	public function testGetPropertiesUnreadProperty($creationDT, $readDT, $expected) {
		$this->comment->expects($this->any())
			->method('getCreationDateTime')
			->willReturn($creationDT);

		$this->comment->expects($this->any())
			->method('getMentions')
			->willReturn([]);

		$this->commentsManager->expects($this->once())
			->method('getReadMark')
			->willReturn($readDT);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn(
				$this->getMockBuilder(IUser::class)
					->disableOriginalConstructor()
					->getMock()
			);

		$properties = $this->node->getProperties(null);

		$this->assertTrue(array_key_exists(CommentNode::PROPERTY_NAME_UNREAD, $properties));
		$this->assertSame($properties[CommentNode::PROPERTY_NAME_UNREAD], $expected);
	}
}
