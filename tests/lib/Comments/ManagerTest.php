<?php

namespace Test\Comments;

use OC\Comments\Comment;
use OC\Comments\Manager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsEventHandler;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IInitialStateService;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use Test\TestCase;

/**
 * Class ManagerTest
 *
 * @group DB
 */
class ManagerTest extends TestCase {
	/** @var IDBConnection */
	private $connection;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();

		$sql = $this->connection->getDatabasePlatform()->getTruncateTableSQL('`*PREFIX*comments`');
		$this->connection->prepare($sql)->execute();
	}

	protected function addDatabaseEntry($parentId, $topmostParentId, $creationDT = null, $latestChildDT = null, $objectId = null) {
		if (is_null($creationDT)) {
			$creationDT = new \DateTime();
		}
		if (is_null($latestChildDT)) {
			$latestChildDT = new \DateTime('yesterday');
		}
		if (is_null($objectId)) {
			$objectId = 'file64';
		}

		$qb = $this->connection->getQueryBuilder();
		$qb
			->insert('comments')
			->values([
				'parent_id' => $qb->createNamedParameter($parentId),
				'topmost_parent_id' => $qb->createNamedParameter($topmostParentId),
				'children_count' => $qb->createNamedParameter(2),
				'actor_type' => $qb->createNamedParameter('users'),
				'actor_id' => $qb->createNamedParameter('alice'),
				'message' => $qb->createNamedParameter('nice one'),
				'verb' => $qb->createNamedParameter('comment'),
				'creation_timestamp' => $qb->createNamedParameter($creationDT, 'datetime'),
				'latest_child_timestamp' => $qb->createNamedParameter($latestChildDT, 'datetime'),
				'object_type' => $qb->createNamedParameter('files'),
				'object_id' => $qb->createNamedParameter($objectId),
			])
			->execute();

		return $qb->getLastInsertId();
	}

	protected function getManager() {
		return new Manager(
			$this->connection,
			$this->createMock(LoggerInterface::class),
			$this->createMock(IConfig::class),
			$this->createMock(ITimeFactory::class),
			$this->createMock(IInitialStateService::class)
		);
	}


	public function testGetCommentNotFound() {
		$this->expectException(\OCP\Comments\NotFoundException::class);

		$manager = $this->getManager();
		$manager->get('22');
	}


	public function testGetCommentNotFoundInvalidInput() {
		$this->expectException(\InvalidArgumentException::class);

		$manager = $this->getManager();
		$manager->get('unexisting22');
	}

	public function testGetComment() {
		$manager = $this->getManager();

		$creationDT = new \DateTime();
		$latestChildDT = new \DateTime('yesterday');

		$qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();
		$qb
			->insert('comments')
			->values([
				'parent_id' => $qb->createNamedParameter('2'),
				'topmost_parent_id' => $qb->createNamedParameter('1'),
				'children_count' => $qb->createNamedParameter(2),
				'actor_type' => $qb->createNamedParameter('users'),
				'actor_id' => $qb->createNamedParameter('alice'),
				'message' => $qb->createNamedParameter('nice one'),
				'verb' => $qb->createNamedParameter('comment'),
				'creation_timestamp' => $qb->createNamedParameter($creationDT, 'datetime'),
				'latest_child_timestamp' => $qb->createNamedParameter($latestChildDT, 'datetime'),
				'object_type' => $qb->createNamedParameter('files'),
				'object_id' => $qb->createNamedParameter('file64'),
			])
			->execute();

		$id = strval($qb->getLastInsertId());

		$comment = $manager->get($id);
		$this->assertTrue($comment instanceof IComment);
		$this->assertSame($comment->getId(), $id);
		$this->assertSame($comment->getParentId(), '2');
		$this->assertSame($comment->getTopmostParentId(), '1');
		$this->assertSame($comment->getChildrenCount(), 2);
		$this->assertSame($comment->getActorType(), 'users');
		$this->assertSame($comment->getActorId(), 'alice');
		$this->assertSame($comment->getMessage(), 'nice one');
		$this->assertSame($comment->getVerb(), 'comment');
		$this->assertSame($comment->getObjectType(), 'files');
		$this->assertSame($comment->getObjectId(), 'file64');
		$this->assertEquals($comment->getCreationDateTime()->getTimestamp(), $creationDT->getTimestamp());
		$this->assertEquals($comment->getLatestChildDateTime(), $latestChildDT);
	}


	public function testGetTreeNotFound() {
		$this->expectException(\OCP\Comments\NotFoundException::class);

		$manager = $this->getManager();
		$manager->getTree('22');
	}


	public function testGetTreeNotFoundInvalidIpnut() {
		$this->expectException(\InvalidArgumentException::class);

		$manager = $this->getManager();
		$manager->getTree('unexisting22');
	}

	public function testGetTree() {
		$headId = $this->addDatabaseEntry(0, 0);

		$this->addDatabaseEntry($headId, $headId, new \DateTime('-3 hours'));
		$this->addDatabaseEntry($headId, $headId, new \DateTime('-2 hours'));
		$id = $this->addDatabaseEntry($headId, $headId, new \DateTime('-1 hour'));

		$manager = $this->getManager();
		$tree = $manager->getTree($headId);

		// Verifying the root comment
		$this->assertTrue(isset($tree['comment']));
		$this->assertTrue($tree['comment'] instanceof IComment);
		$this->assertSame($tree['comment']->getId(), strval($headId));
		$this->assertTrue(isset($tree['replies']));
		$this->assertSame(count($tree['replies']), 3);

		// one level deep
		foreach ($tree['replies'] as $reply) {
			$this->assertTrue($reply['comment'] instanceof IComment);
			$this->assertSame($reply['comment']->getId(), strval($id));
			$this->assertSame(count($reply['replies']), 0);
			$id--;
		}
	}

	public function testGetTreeNoReplies() {
		$id = $this->addDatabaseEntry(0, 0);

		$manager = $this->getManager();
		$tree = $manager->getTree($id);

		// Verifying the root comment
		$this->assertTrue(isset($tree['comment']));
		$this->assertTrue($tree['comment'] instanceof IComment);
		$this->assertSame($tree['comment']->getId(), strval($id));
		$this->assertTrue(isset($tree['replies']));
		$this->assertSame(count($tree['replies']), 0);

		// one level deep
		foreach ($tree['replies'] as $reply) {
			throw new \Exception('This ain`t happen');
		}
	}

	public function testGetTreeWithLimitAndOffset() {
		$headId = $this->addDatabaseEntry(0, 0);

		$this->addDatabaseEntry($headId, $headId, new \DateTime('-3 hours'));
		$this->addDatabaseEntry($headId, $headId, new \DateTime('-2 hours'));
		$this->addDatabaseEntry($headId, $headId, new \DateTime('-1 hour'));
		$idToVerify = $this->addDatabaseEntry($headId, $headId, new \DateTime());

		$manager = $this->getManager();

		for ($offset = 0; $offset < 3; $offset += 2) {
			$tree = $manager->getTree(strval($headId), 2, $offset);

			// Verifying the root comment
			$this->assertTrue(isset($tree['comment']));
			$this->assertTrue($tree['comment'] instanceof IComment);
			$this->assertSame($tree['comment']->getId(), strval($headId));
			$this->assertTrue(isset($tree['replies']));
			$this->assertSame(count($tree['replies']), 2);

			// one level deep
			foreach ($tree['replies'] as $reply) {
				$this->assertTrue($reply['comment'] instanceof IComment);
				$this->assertSame($reply['comment']->getId(), strval($idToVerify));
				$this->assertSame(count($reply['replies']), 0);
				$idToVerify--;
			}
		}
	}

	public function testGetForObject() {
		$this->addDatabaseEntry(0, 0);

		$manager = $this->getManager();
		$comments = $manager->getForObject('files', 'file64');

		$this->assertTrue(is_array($comments));
		$this->assertSame(count($comments), 1);
		$this->assertTrue($comments[0] instanceof IComment);
		$this->assertSame($comments[0]->getMessage(), 'nice one');
	}

	public function testGetForObjectWithLimitAndOffset() {
		$this->addDatabaseEntry(0, 0, new \DateTime('-6 hours'));
		$this->addDatabaseEntry(0, 0, new \DateTime('-5 hours'));
		$this->addDatabaseEntry(1, 1, new \DateTime('-4 hours'));
		$this->addDatabaseEntry(0, 0, new \DateTime('-3 hours'));
		$this->addDatabaseEntry(2, 2, new \DateTime('-2 hours'));
		$this->addDatabaseEntry(2, 2, new \DateTime('-1 hours'));
		$idToVerify = $this->addDatabaseEntry(3, 1, new \DateTime());

		$manager = $this->getManager();
		$offset = 0;
		do {
			$comments = $manager->getForObject('files', 'file64', 3, $offset);

			$this->assertTrue(is_array($comments));
			foreach ($comments as $comment) {
				$this->assertTrue($comment instanceof IComment);
				$this->assertSame($comment->getMessage(), 'nice one');
				$this->assertSame($comment->getId(), strval($idToVerify));
				$idToVerify--;
			}
			$offset += 3;
		} while (count($comments) > 0);
	}

	public function testGetForObjectWithDateTimeConstraint() {
		$this->addDatabaseEntry(0, 0, new \DateTime('-6 hours'));
		$this->addDatabaseEntry(0, 0, new \DateTime('-5 hours'));
		$id1 = $this->addDatabaseEntry(0, 0, new \DateTime('-3 hours'));
		$id2 = $this->addDatabaseEntry(2, 2, new \DateTime('-2 hours'));

		$manager = $this->getManager();
		$comments = $manager->getForObject('files', 'file64', 0, 0, new \DateTime('-4 hours'));

		$this->assertSame(count($comments), 2);
		$this->assertSame($comments[0]->getId(), strval($id2));
		$this->assertSame($comments[1]->getId(), strval($id1));
	}

	public function testGetForObjectWithLimitAndOffsetAndDateTimeConstraint() {
		$this->addDatabaseEntry(0, 0, new \DateTime('-7 hours'));
		$this->addDatabaseEntry(0, 0, new \DateTime('-6 hours'));
		$this->addDatabaseEntry(1, 1, new \DateTime('-5 hours'));
		$this->addDatabaseEntry(0, 0, new \DateTime('-3 hours'));
		$this->addDatabaseEntry(2, 2, new \DateTime('-2 hours'));
		$this->addDatabaseEntry(2, 2, new \DateTime('-1 hours'));
		$idToVerify = $this->addDatabaseEntry(3, 1, new \DateTime());

		$manager = $this->getManager();
		$offset = 0;
		do {
			$comments = $manager->getForObject('files', 'file64', 3, $offset, new \DateTime('-4 hours'));

			$this->assertTrue(is_array($comments));
			foreach ($comments as $comment) {
				$this->assertTrue($comment instanceof IComment);
				$this->assertSame($comment->getMessage(), 'nice one');
				$this->assertSame($comment->getId(), strval($idToVerify));
				$this->assertTrue(intval($comment->getId()) >= 4);
				$idToVerify--;
			}
			$offset += 3;
		} while (count($comments) > 0);
	}

	public function testGetNumberOfCommentsForObject() {
		for ($i = 1; $i < 5; $i++) {
			$this->addDatabaseEntry(0, 0);
		}

		$manager = $this->getManager();

		$amount = $manager->getNumberOfCommentsForObject('untype', '00');
		$this->assertSame($amount, 0);

		$amount = $manager->getNumberOfCommentsForObject('files', 'file64');
		$this->assertSame($amount, 4);
	}

	public function testGetNumberOfUnreadCommentsForFolder() {
		$query = $this->connection->getQueryBuilder();
		$query->insert('filecache')
			->values([
				'parent' => $query->createNamedParameter(1000),
				'size' => $query->createNamedParameter(10),
				'mtime' => $query->createNamedParameter(10),
				'storage_mtime' => $query->createNamedParameter(10),
				'path' => $query->createParameter('path'),
				'path_hash' => $query->createParameter('path'),
			]);

		$fileIds = [];
		for ($i = 0; $i < 4; $i++) {
			$query->setParameter('path', 'path_' . $i);
			$query->execute();
			$fileIds[] = $query->getLastInsertId();
		}

		// 2 comment for 1111 with 1 before read marker
		// 2 comments for 1112 with no read marker
		// 1 comment for 1113 before read marker
		// 1 comment for 1114 with no read marker
		$this->addDatabaseEntry(0, 0, null, null, $fileIds[1]);
		for ($i = 0; $i < 4; $i++) {
			$this->addDatabaseEntry(0, 0, null, null, $fileIds[$i]);
		}
		$this->addDatabaseEntry(0, 0, (new \DateTime())->modify('-2 days'), null, $fileIds[0]);
		/** @var IUser|\PHPUnit\Framework\MockObject\MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('comment_test');

		$manager = $this->getManager();

		$manager->setReadMark('files', (string) $fileIds[0], (new \DateTime())->modify('-1 days'), $user);
		$manager->setReadMark('files', (string) $fileIds[2], (new \DateTime()), $user);

		$amount = $manager->getNumberOfUnreadCommentsForFolder(1000, $user);
		$this->assertEquals([
			$fileIds[0] => 1,
			$fileIds[1] => 2,
			$fileIds[3] => 1,
		], $amount);
	}

	/**
	 * @dataProvider dataGetForObjectSince
	 * @param $lastKnown
	 * @param $order
	 * @param $limit
	 * @param $resultFrom
	 * @param $resultTo
	 */
	public function testGetForObjectSince($lastKnown, $order, $limit, $resultFrom, $resultTo) {
		$ids = [];
		$ids[] = $this->addDatabaseEntry(0, 0);
		$ids[] = $this->addDatabaseEntry(0, 0);
		$ids[] = $this->addDatabaseEntry(0, 0);
		$ids[] = $this->addDatabaseEntry(0, 0);
		$ids[] = $this->addDatabaseEntry(0, 0);

		$manager = $this->getManager();
		$comments = $manager->getForObjectSince('files', 'file64', ($lastKnown === null ? 0 : $ids[$lastKnown]), $order, $limit);

		$expected = array_slice($ids, $resultFrom, $resultTo - $resultFrom + 1);
		if ($order === 'desc') {
			$expected = array_reverse($expected);
		}

		$this->assertSame($expected, array_map(function (IComment $c) {
			return (int) $c->getId();
		}, $comments));
	}

	public function dataGetForObjectSince() {
		return [
			[null, 'asc', 20, 0, 4],
			[null, 'asc', 2, 0, 1],
			[null, 'desc', 20, 0, 4],
			[null, 'desc', 2, 3, 4],
			[1, 'asc', 20, 2, 4],
			[1, 'asc', 2, 2, 3],
			[3, 'desc', 20, 0, 2],
			[3, 'desc', 2, 1, 2],
		];
	}

	public function invalidCreateArgsProvider() {
		return [
			['', 'aId-1', 'oType-1', 'oId-1'],
			['aType-1', '', 'oType-1', 'oId-1'],
			['aType-1', 'aId-1', '', 'oId-1'],
			['aType-1', 'aId-1', 'oType-1', ''],
			[1, 'aId-1', 'oType-1', 'oId-1'],
			['aType-1', 1, 'oType-1', 'oId-1'],
			['aType-1', 'aId-1', 1, 'oId-1'],
			['aType-1', 'aId-1', 'oType-1', 1],
		];
	}

	/**
	 * @dataProvider invalidCreateArgsProvider
	 * @param string $aType
	 * @param string $aId
	 * @param string $oType
	 * @param string $oId
	 */
	public function testCreateCommentInvalidArguments($aType, $aId, $oType, $oId) {
		$this->expectException(\InvalidArgumentException::class);

		$manager = $this->getManager();
		$manager->create($aType, $aId, $oType, $oId);
	}

	public function testCreateComment() {
		$actorType = 'bot';
		$actorId = 'bob';
		$objectType = 'weather';
		$objectId = 'bielefeld';

		$comment = $this->getManager()->create($actorType, $actorId, $objectType, $objectId);
		$this->assertTrue($comment instanceof IComment);
		$this->assertSame($comment->getActorType(), $actorType);
		$this->assertSame($comment->getActorId(), $actorId);
		$this->assertSame($comment->getObjectType(), $objectType);
		$this->assertSame($comment->getObjectId(), $objectId);
	}


	public function testDelete() {
		$this->expectException(\OCP\Comments\NotFoundException::class);

		$manager = $this->getManager();

		$done = $manager->delete('404');
		$this->assertFalse($done);

		$done = $manager->delete('%');
		$this->assertFalse($done);

		$done = $manager->delete('');
		$this->assertFalse($done);

		$id = strval($this->addDatabaseEntry(0, 0));
		$comment = $manager->get($id);
		$this->assertTrue($comment instanceof IComment);
		$done = $manager->delete($id);
		$this->assertTrue($done);
		$manager->get($id);
	}

	public function testSaveNew() {
		$manager = $this->getManager();
		$comment = new Comment();
		$comment
			->setActor('users', 'alice')
			->setObject('files', 'file64')
			->setMessage('very beautiful, I am impressed!')
			->setVerb('comment');

		$saveSuccessful = $manager->save($comment);
		$this->assertTrue($saveSuccessful);
		$this->assertTrue($comment->getId() !== '');
		$this->assertTrue($comment->getId() !== '0');
		$this->assertTrue(!is_null($comment->getCreationDateTime()));

		$loadedComment = $manager->get($comment->getId());
		$this->assertSame($comment->getMessage(), $loadedComment->getMessage());
		$this->assertEquals($comment->getCreationDateTime()->getTimestamp(), $loadedComment->getCreationDateTime()->getTimestamp());
	}

	public function testSaveUpdate() {
		$manager = $this->getManager();
		$comment = new Comment();
		$comment
			->setActor('users', 'alice')
			->setObject('files', 'file64')
			->setMessage('very beautiful, I am impressed!')
			->setVerb('comment');

		$manager->save($comment);

		$comment->setMessage('very beautiful, I am really so much impressed!');
		$manager->save($comment);

		$loadedComment = $manager->get($comment->getId());
		$this->assertSame($comment->getMessage(), $loadedComment->getMessage());
	}


	public function testSaveUpdateException() {
		$this->expectException(\OCP\Comments\NotFoundException::class);

		$manager = $this->getManager();
		$comment = new Comment();
		$comment
			->setActor('users', 'alice')
			->setObject('files', 'file64')
			->setMessage('very beautiful, I am impressed!')
			->setVerb('comment');

		$manager->save($comment);

		$manager->delete($comment->getId());
		$comment->setMessage('very beautiful, I am really so much impressed!');
		$manager->save($comment);
	}


	public function testSaveIncomplete() {
		$this->expectException(\UnexpectedValueException::class);

		$manager = $this->getManager();
		$comment = new Comment();
		$comment->setMessage('from no one to nothing');
		$manager->save($comment);
	}

	public function testSaveAsChild() {
		$id = $this->addDatabaseEntry(0, 0);

		$manager = $this->getManager();

		for ($i = 0; $i < 3; $i++) {
			$comment = new Comment();
			$comment
				->setActor('users', 'alice')
				->setObject('files', 'file64')
				->setParentId(strval($id))
				->setMessage('full ack')
				->setVerb('comment')
				// setting the creation time avoids using sleep() while making sure to test with different timestamps
				->setCreationDateTime(new \DateTime('+' . $i . ' minutes'));

			$manager->save($comment);

			$this->assertSame($comment->getTopmostParentId(), strval($id));
			$parentComment = $manager->get(strval($id));
			$this->assertSame($parentComment->getChildrenCount(), $i + 1);
			$this->assertEquals($parentComment->getLatestChildDateTime()->getTimestamp(), $comment->getCreationDateTime()->getTimestamp());
		}
	}

	public function invalidActorArgsProvider() {
		return
			[
				['', ''],
				[1, 'alice'],
				['users', 1],
			];
	}

	/**
	 * @dataProvider invalidActorArgsProvider
	 * @param string $type
	 * @param string $id
	 */
	public function testDeleteReferencesOfActorInvalidInput($type, $id) {
		$this->expectException(\InvalidArgumentException::class);

		$manager = $this->getManager();
		$manager->deleteReferencesOfActor($type, $id);
	}

	public function testDeleteReferencesOfActor() {
		$ids = [];
		$ids[] = $this->addDatabaseEntry(0, 0);
		$ids[] = $this->addDatabaseEntry(0, 0);
		$ids[] = $this->addDatabaseEntry(0, 0);

		$manager = $this->getManager();

		// just to make sure they are really set, with correct actor data
		$comment = $manager->get(strval($ids[1]));
		$this->assertSame($comment->getActorType(), 'users');
		$this->assertSame($comment->getActorId(), 'alice');

		$wasSuccessful = $manager->deleteReferencesOfActor('users', 'alice');
		$this->assertTrue($wasSuccessful);

		foreach ($ids as $id) {
			$comment = $manager->get(strval($id));
			$this->assertSame($comment->getActorType(), ICommentsManager::DELETED_USER);
			$this->assertSame($comment->getActorId(), ICommentsManager::DELETED_USER);
		}

		// actor info is gone from DB, but when database interaction is alright,
		// we still expect to get true back
		$wasSuccessful = $manager->deleteReferencesOfActor('users', 'alice');
		$this->assertTrue($wasSuccessful);
	}

	public function testDeleteReferencesOfActorWithUserManagement() {
		$user = \OC::$server->getUserManager()->createUser('xenia', '123456');
		$this->assertTrue($user instanceof IUser);

		$manager = \OC::$server->getCommentsManager();
		$comment = $manager->create('users', $user->getUID(), 'files', 'file64');
		$comment
			->setMessage('Most important comment I ever left on the Internet.')
			->setVerb('comment');
		$status = $manager->save($comment);
		$this->assertTrue($status);

		$commentID = $comment->getId();
		$user->delete();

		$comment = $manager->get($commentID);
		$this->assertSame($comment->getActorType(), ICommentsManager::DELETED_USER);
		$this->assertSame($comment->getActorId(), ICommentsManager::DELETED_USER);
	}

	public function invalidObjectArgsProvider() {
		return
			[
				['', ''],
				[1, 'file64'],
				['files', 1],
			];
	}

	/**
	 * @dataProvider invalidObjectArgsProvider
	 * @param string $type
	 * @param string $id
	 */
	public function testDeleteCommentsAtObjectInvalidInput($type, $id) {
		$this->expectException(\InvalidArgumentException::class);

		$manager = $this->getManager();
		$manager->deleteCommentsAtObject($type, $id);
	}

	public function testDeleteCommentsAtObject() {
		$ids = [];
		$ids[] = $this->addDatabaseEntry(0, 0);
		$ids[] = $this->addDatabaseEntry(0, 0);
		$ids[] = $this->addDatabaseEntry(0, 0);

		$manager = $this->getManager();

		// just to make sure they are really set, with correct actor data
		$comment = $manager->get(strval($ids[1]));
		$this->assertSame($comment->getObjectType(), 'files');
		$this->assertSame($comment->getObjectId(), 'file64');

		$wasSuccessful = $manager->deleteCommentsAtObject('files', 'file64');
		$this->assertTrue($wasSuccessful);

		$verified = 0;
		foreach ($ids as $id) {
			try {
				$manager->get(strval($id));
			} catch (NotFoundException $e) {
				$verified++;
			}
		}
		$this->assertSame($verified, 3);

		// actor info is gone from DB, but when database interaction is alright,
		// we still expect to get true back
		$wasSuccessful = $manager->deleteCommentsAtObject('files', 'file64');
		$this->assertTrue($wasSuccessful);
	}

	public function testSetMarkRead() {
		/** @var IUser|\PHPUnit\Framework\MockObject\MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('alice');

		$dateTimeSet = new \DateTime();

		$manager = $this->getManager();
		$manager->setReadMark('robot', '36', $dateTimeSet, $user);

		$dateTimeGet = $manager->getReadMark('robot', '36', $user);

		$this->assertEquals($dateTimeGet->getTimestamp(), $dateTimeSet->getTimestamp());
	}

	public function testSetMarkReadUpdate() {
		/** @var IUser|\PHPUnit\Framework\MockObject\MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('alice');

		$dateTimeSet = new \DateTime('yesterday');

		$manager = $this->getManager();
		$manager->setReadMark('robot', '36', $dateTimeSet, $user);

		$dateTimeSet = new \DateTime('today');
		$manager->setReadMark('robot', '36', $dateTimeSet, $user);

		$dateTimeGet = $manager->getReadMark('robot', '36', $user);

		$this->assertEquals($dateTimeGet, $dateTimeSet);
	}

	public function testReadMarkDeleteUser() {
		/** @var IUser|\PHPUnit\Framework\MockObject\MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('alice');

		$dateTimeSet = new \DateTime();

		$manager = $this->getManager();
		$manager->setReadMark('robot', '36', $dateTimeSet, $user);

		$manager->deleteReadMarksFromUser($user);
		$dateTimeGet = $manager->getReadMark('robot', '36', $user);

		$this->assertNull($dateTimeGet);
	}

	public function testReadMarkDeleteObject() {
		/** @var IUser|\PHPUnit\Framework\MockObject\MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('alice');

		$dateTimeSet = new \DateTime();

		$manager = $this->getManager();
		$manager->setReadMark('robot', '36', $dateTimeSet, $user);

		$manager->deleteReadMarksOnObject('robot', '36');
		$dateTimeGet = $manager->getReadMark('robot', '36', $user);

		$this->assertNull($dateTimeGet);
	}

	public function testSendEvent() {
		$handler1 = $this->getMockBuilder(ICommentsEventHandler::class)->getMock();
		$handler1->expects($this->exactly(4))
			->method('handle');

		$handler2 = $this->getMockBuilder(ICommentsEventHandler::class)->getMock();
		$handler1->expects($this->exactly(4))
			->method('handle');

		$manager = $this->getManager();
		$manager->registerEventHandler(function () use ($handler1) {
			return $handler1;
		});
		$manager->registerEventHandler(function () use ($handler2) {
			return $handler2;
		});

		$comment = new Comment();
		$comment
			->setActor('users', 'alice')
			->setObject('files', 'file64')
			->setMessage('very beautiful, I am impressed!')
			->setVerb('comment');

		// Add event
		$manager->save($comment);

		// Update event
		$comment->setMessage('Different topic');
		$manager->save($comment);

		// Delete event
		$manager->delete($comment->getId());
	}

	public function testResolveDisplayName() {
		$manager = $this->getManager();

		$planetClosure = function ($name) {
			return ucfirst($name);
		};

		$galaxyClosure = function ($name) {
			return strtoupper($name);
		};

		$manager->registerDisplayNameResolver('planet', $planetClosure);
		$manager->registerDisplayNameResolver('galaxy', $galaxyClosure);

		$this->assertSame('Neptune', $manager->resolveDisplayName('planet', 'neptune'));
		$this->assertSame('SOMBRERO', $manager->resolveDisplayName('galaxy', 'sombrero'));
	}


	public function testRegisterResolverDuplicate() {
		$this->expectException(\OutOfBoundsException::class);

		$manager = $this->getManager();

		$planetClosure = function ($name) {
			return ucfirst($name);
		};
		$manager->registerDisplayNameResolver('planet', $planetClosure);
		$manager->registerDisplayNameResolver('planet', $planetClosure);
	}


	public function testRegisterResolverInvalidType() {
		$this->expectException(\InvalidArgumentException::class);

		$manager = $this->getManager();

		$planetClosure = function ($name) {
			return ucfirst($name);
		};
		$manager->registerDisplayNameResolver(1337, $planetClosure);
	}


	public function testResolveDisplayNameUnregisteredType() {
		$this->expectException(\OutOfBoundsException::class);

		$manager = $this->getManager();

		$planetClosure = function ($name) {
			return ucfirst($name);
		};

		$manager->registerDisplayNameResolver('planet', $planetClosure);
		$manager->resolveDisplayName('galaxy', 'sombrero');
	}

	public function testResolveDisplayNameDirtyResolver() {
		$manager = $this->getManager();

		$planetClosure = function () {
			return null;
		};

		$manager->registerDisplayNameResolver('planet', $planetClosure);
		$this->assertTrue(is_string($manager->resolveDisplayName('planet', 'neptune')));
	}


	public function testResolveDisplayNameInvalidType() {
		$this->expectException(\InvalidArgumentException::class);

		$manager = $this->getManager();

		$planetClosure = function () {
			return null;
		};

		$manager->registerDisplayNameResolver('planet', $planetClosure);
		$this->assertTrue(is_string($manager->resolveDisplayName(1337, 'neptune')));
	}
}
