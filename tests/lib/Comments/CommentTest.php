<?php

namespace Test\Comments;

use OC\Comments\Comment;
use OCP\Comments\IComment;
use Test\TestCase;

class CommentTest extends TestCase {

	/**
	 * @throws \OCP\Comments\IllegalIDChangeException
	 */
	public function testSettersValidInput() {
		$comment = new Comment();

		$id = 'comment23';
		$parentId = 'comment11.5';
		$topMostParentId = 'comment11.0';
		$childrenCount = 6;
		$message = 'I like to comment comment';
		$verb = 'comment';
		$actor = ['type' => 'users', 'id' => 'alice'];
		$creationDT = new \DateTime();
		$latestChildDT = new \DateTime('yesterday');
		$object = ['type' => 'files', 'id' => 'file64'];

		$comment
			->setId($id)
			->setParentId($parentId)
			->setTopmostParentId($topMostParentId)
			->setChildrenCount($childrenCount)
			->setMessage($message)
			->setVerb($verb)
			->setActor($actor['type'], $actor['id'])
			->setCreationDateTime($creationDT)
			->setLatestChildDateTime($latestChildDT)
			->setObject($object['type'], $object['id']);

		$this->assertSame($id, $comment->getId());
		$this->assertSame($parentId, $comment->getParentId());
		$this->assertSame($topMostParentId, $comment->getTopmostParentId());
		$this->assertSame($childrenCount, $comment->getChildrenCount());
		$this->assertSame($message, $comment->getMessage());
		$this->assertSame($verb, $comment->getVerb());
		$this->assertSame($actor['type'], $comment->getActorType());
		$this->assertSame($actor['id'], $comment->getActorId());
		$this->assertSame($creationDT, $comment->getCreationDateTime());
		$this->assertSame($latestChildDT, $comment->getLatestChildDateTime());
		$this->assertSame($object['type'], $comment->getObjectType());
		$this->assertSame($object['id'], $comment->getObjectId());
	}


	public function testSetIdIllegalInput() {
		$this->expectException(\OCP\Comments\IllegalIDChangeException::class);

		$comment = new Comment();

		$comment->setId('c23');
		$comment->setId('c17');
	}

	/**
	 * @throws \OCP\Comments\IllegalIDChangeException
	 */
	public function testResetId() {
		$comment = new Comment();
		$comment->setId('c23');
		$comment->setId('');

		$this->assertSame('', $comment->getId());
	}

	public function simpleSetterProvider() {
		return [
			['Id', true],
			['TopmostParentId', true],
			['ParentId', true],
			['Message', true],
			['Verb', true],
			['Verb', ''],
			['ChildrenCount', true],
		];
	}

	/**
	 * @dataProvider simpleSetterProvider
	 */
	public function testSimpleSetterInvalidInput($field, $input) {
		$this->expectException(\InvalidArgumentException::class);

		$comment = new Comment();
		$setter = 'set' . $field;

		$comment->$setter($input);
	}

	public function roleSetterProvider() {
		return [
			['Actor', true, true],
			['Actor', 'users', true],
			['Actor', true, 'alice'],
			['Actor', ' ', ' '],
			['Object', true, true],
			['Object', 'files', true],
			['Object', true, 'file64'],
			['Object', ' ', ' '],
		];
	}

	/**
	 * @dataProvider roleSetterProvider
	 */
	public function testSetRoleInvalidInput($role, $type, $id) {
		$this->expectException(\InvalidArgumentException::class);

		$comment = new Comment();
		$setter = 'set' . $role;
		$comment->$setter($type, $id);
	}


	public function testSetUberlongMessage() {
		$this->expectException(\OCP\Comments\MessageTooLongException::class);

		$comment = new Comment();
		$msg = str_pad('', IComment::MAX_MESSAGE_LENGTH + 1, 'x');
		$comment->setMessage($msg);
	}

	public function mentionsProvider() {
		return [
			[
				'@alice @bob look look, a cook!', ['alice', 'bob']
			],
			[
				'no mentions in this message', []
			],
			[
				'@alice @bob look look, a duplication @alice test @bob!', ['alice', 'bob']
			],
			[
				'@alice is the author, notify @bob, nevertheless mention her!', ['alice', 'bob'], 'alice'
			],
			[
				'@foobar and @barfoo you should know, @foo@bar.com is valid' .
					' and so is @bar@foo.org@foobar.io I hope that clarifies everything.' .
					' cc @23452-4333-54353-2342 @yolo!' .
					' however the most important thing to know is that www.croissant.com/@oil is not valid' .
					' and won\'t match anything at all',
				['bar@foo.org@foobar.io', '23452-4333-54353-2342', 'foo@bar.com', 'foobar', 'barfoo', 'yolo']
			],
			[
				'@@chef is also a valid mention, no matter how strange it looks', ['@chef']
			],
			[
				'Also @"user with spaces" are now supported', ['user with spaces']
			],
			[
				'Also @"guest/0123456789abcdef" are now supported', [], null, ['guest/0123456789abcdef']
			],
		];
	}

	/**
	 * @dataProvider mentionsProvider
	 *
	 * @param string $message
	 * @param array $expectedUids
	 * @param string|null $author
	 * @param array $expectedGuests
	 */
	public function testMentions(string $message, array $expectedUids, ?string $author = null, array $expectedGuests = []): void {
		$comment = new Comment();
		$comment->setMessage($message);
		if (!is_null($author)) {
			$comment->setActor('user', $author);
		}
		$mentions = $comment->getMentions();
		while ($mention = array_shift($mentions)) {
			if ($mention['type'] === 'user') {
				$id = array_shift($expectedUids);
			} elseif ($mention['type'] === 'guest') {
				$id = array_shift($expectedGuests);
			} else {
				$this->fail('Unexpected mention type');
				continue;
			}
			$this->assertSame($id, $mention['id']);
		}
		$this->assertEmpty($mentions);
		$this->assertEmpty($expectedUids);
	}
}
