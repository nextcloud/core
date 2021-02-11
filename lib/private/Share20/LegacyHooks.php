<?php
/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
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

namespace OC\Share20;

use OCP\Files\File;
use OCP\Share;
use OCP\Share\IShare;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class LegacyHooks {

	/** @var EventDispatcherInterface */
	private $eventDispatcher;

	/**
	 * LegacyHooks constructor.
	 *
	 * @param EventDispatcherInterface $eventDispatcher
	 */
	public function __construct(EventDispatcherInterface $eventDispatcher) {
		$this->eventDispatcher = $eventDispatcher;

		$this->eventDispatcher->addListener('OCP\Share::preUnshare', [$this, 'preUnshare']);
		$this->eventDispatcher->addListener('OCP\Share::postUnshare', [$this, 'postUnshare']);
		$this->eventDispatcher->addListener('OCP\Share::postUnshareFromSelf', [$this, 'postUnshareFromSelf']);
		$this->eventDispatcher->addListener('OCP\Share::preShare', [$this, 'preShare']);
		$this->eventDispatcher->addListener('OCP\Share::postShare', [$this, 'postShare']);
	}

	/**
	 * @param GenericEvent $e
	 */
	public function preUnshare(GenericEvent $e) {
		/** @var IShare $share */
		$share = $e->getSubject();

		$formatted = $this->formatHookParams($share);
		\OC_Hook::emit(Share::class, 'pre_unshare', $formatted);
	}

	/**
	 * @param GenericEvent $e
	 */
	public function postUnshare(GenericEvent $e) {
		/** @var IShare $share */
		$share = $e->getSubject();

		$formatted = $this->formatHookParams($share);

		/** @var IShare[] $deletedShares */
		$deletedShares = $e->getArgument('deletedShares');

		$formattedDeletedShares = array_map(function ($share) {
			return $this->formatHookParams($share);
		}, $deletedShares);

		$formatted['deletedShares'] = $formattedDeletedShares;

		\OC_Hook::emit(Share::class, 'post_unshare', $formatted);
	}

	/**
	 * @param GenericEvent $e
	 */
	public function postUnshareFromSelf(GenericEvent $e) {
		/** @var IShare $share */
		$share = $e->getSubject();

		$formatted = $this->formatHookParams($share);
		$formatted['itemTarget'] = $formatted['fileTarget'];
		$formatted['unsharedItems'] = [$formatted];

		\OC_Hook::emit(Share::class, 'post_unshareFromSelf', $formatted);
	}

	private function formatHookParams(IShare $share) {
		// Prepare hook
		$shareType = $share->getShareType();
		$sharedWith = '';
		if ($shareType === IShare::TYPE_USER ||
			$shareType === IShare::TYPE_GROUP ||
			$shareType === IShare::TYPE_REMOTE) {
			$sharedWith = $share->getSharedWith();
		}

		$hookParams = [
			'id' => $share->getId(),
			'itemType' => $share->getNodeType(),
			'itemSource' => $share->getNodeId(),
			'shareType' => $shareType,
			'shareWith' => $sharedWith,
			'itemparent' => method_exists($share, 'getParent') ? $share->getParent() : '',
			'uidOwner' => $share->getSharedBy(),
			'fileSource' => $share->getNodeId(),
			'fileTarget' => $share->getTarget()
		];
		return $hookParams;
	}

	public function preShare(GenericEvent $e) {
		/** @var IShare $share */
		$share = $e->getSubject();

		// Pre share hook
		$run = true;
		$error = '';
		$preHookData = [
			'itemType' => $share->getNode() instanceof File ? 'file' : 'folder',
			'itemSource' => $share->getNode()->getId(),
			'shareType' => $share->getShareType(),
			'uidOwner' => $share->getSharedBy(),
			'permissions' => $share->getPermissions(),
			'fileSource' => $share->getNode()->getId(),
			'expiration' => $share->getExpirationDate(),
			'token' => $share->getToken(),
			'itemTarget' => $share->getTarget(),
			'shareWith' => $share->getSharedWith(),
			'run' => &$run,
			'error' => &$error,
		];
		\OC_Hook::emit(Share::class, 'pre_shared', $preHookData);

		if ($run === false) {
			$e->setArgument('error', $error);
			$e->stopPropagation();
		}

		return $e;
	}

	public function postShare(GenericEvent $e) {
		/** @var IShare $share */
		$share = $e->getSubject();

		$postHookData = [
			'itemType' => $share->getNode() instanceof File ? 'file' : 'folder',
			'itemSource' => $share->getNode()->getId(),
			'shareType' => $share->getShareType(),
			'uidOwner' => $share->getSharedBy(),
			'permissions' => $share->getPermissions(),
			'fileSource' => $share->getNode()->getId(),
			'expiration' => $share->getExpirationDate(),
			'token' => $share->getToken(),
			'id' => $share->getId(),
			'shareWith' => $share->getSharedWith(),
			'itemTarget' => $share->getTarget(),
			'fileTarget' => $share->getTarget(),
		];

		\OC_Hook::emit(Share::class, 'post_shared', $postHookData);
	}
}
