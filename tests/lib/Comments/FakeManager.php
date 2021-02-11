<?php

namespace Test\Comments;

use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IUser;

/**
 * Class FakeManager
 */
class FakeManager implements ICommentsManager {
	public function get($id) {
	}

	public function getTree($id, $limit = 0, $offset = 0) {
	}

	public function getForObject(
		$objectType,
		$objectId,
		$limit = 0,
		$offset = 0,
		\DateTime $notOlderThan = null
	) {
	}

	public function getForObjectSince(
		string $objectType,
		string $objectId,
		int $lastKnownCommentId,
		string $sortDirection = 'asc',
		int $limit = 30,
		bool $includeLastKnown = false
	): array {
		return [];
	}

	public function getNumberOfCommentsForObject($objectType, $objectId, \DateTime $notOlderThan = null, $verb = '') {
	}

	public function search(string $search, string $objectType, string $objectId, string $verb, int $offset, int $limit = 50): array {
		return [];
	}

	public function create($actorType, $actorId, $objectType, $objectId) {
	}

	public function delete($id) {
	}

	public function save(IComment $comment) {
	}

	public function deleteReferencesOfActor($actorType, $actorId) {
	}

	public function deleteCommentsAtObject($objectType, $objectId) {
	}

	public function setReadMark($objectType, $objectId, \DateTime $dateTime, IUser $user) {
	}

	public function getReadMark($objectType, $objectId, IUser $user) {
	}

	public function deleteReadMarksFromUser(IUser $user) {
	}

	public function deleteReadMarksOnObject($objectType, $objectId) {
	}

	public function registerEventHandler(\Closure $closure) {
	}

	public function registerDisplayNameResolver($type, \Closure $closure) {
	}

	public function resolveDisplayName($type, $id) {
	}

	public function getNumberOfUnreadCommentsForFolder($folderId, IUser $user) {
	}

	public function getNumberOfUnreadCommentsForObjects(string $objectType, array $objectIds, IUser $user, $verb = ''): array {
		return [];
	}


	public function getActorsInTree($id) {
	}

	public function load(): void {
	}

	public function searchForObjects(string $search, string $objectType, array $objectIds, string $verb, int $offset, int $limit = 50): array {
		return [];
	}

	public function getNumberOfCommentsForObjectSinceComment(string $objectType, string $objectId, int $lastRead, string $verb = ''): int {
		return 0;
	}

	public function getLastCommentBeforeDate(string $objectType, string $objectId, \DateTime $beforeDate, string $verb = ''): int {
		return 0;
	}

	public function getLastCommentDateByActor(string $objectType, string $objectId, string $verb, string $actorType, array $actors): array {
		return [];
	}
}
