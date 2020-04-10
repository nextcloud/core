<?php
/**
 *
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Comments\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Notification\IManager;

/**
 * Class Notifications
 *
 * @package OCA\Comments\Controller
 */
class Notifications extends Controller {
	/** @var IRootFolder  */
	protected $rootFolder;

	/** @var ICommentsManager  */
	protected $commentsManager;

	/** @var IURLGenerator  */
	protected $urlGenerator;

	/** @var IManager  */
	protected $notificationManager;

	/** @var IUserSession  */
	protected $userSession;

	/**
	 * Notifications constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param ICommentsManager $commentsManager
	 * @param IRootFolder $rootFolder
	 * @param IURLGenerator $urlGenerator
	 * @param IManager $notificationManager
	 * @param IUserSession $userSession
	 */
	public function __construct(
		$appName,
		IRequest $request,
		ICommentsManager $commentsManager,
		IRootFolder $rootFolder,
		IURLGenerator $urlGenerator,
		IManager $notificationManager,
		IUserSession $userSession
	) {
		parent::__construct($appName, $request);
		$this->commentsManager = $commentsManager;
		$this->rootFolder = $rootFolder;
		$this->urlGenerator = $urlGenerator;
		$this->notificationManager = $notificationManager;
		$this->userSession = $userSession;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $id		the comment ID
	 * @return Response
	 */
	public function view($id) {
		$currentUser = $this->userSession->getUser();
		if (!$currentUser instanceof IUser) {
			return new RedirectResponse(
				$this->urlGenerator->linkToRoute('core.login.showLoginForm', [
					'redirect_url' => $this->urlGenerator->linkToRoute(
						'comments.Notifications.view',
						['id' => $id]
					),
				])
			);
		}

		try {
			$comment = $this->commentsManager->get($id);
			if($comment->getObjectType() !== 'files') {
				return new NotFoundResponse();
			}
			$userFolder = $this->rootFolder->getUserFolder($currentUser->getUID());
			$files = $userFolder->getById((int)$comment->getObjectId());

			$this->markProcessed($comment, $currentUser);

			if (empty($files)) {
				return new NotFoundResponse();
			}

			$url = $this->urlGenerator->linkToRouteAbsolute(
				'files.viewcontroller.showFile',
				[ 'fileid' => $comment->getObjectId() ]
			);

			return new RedirectResponse($url);
		} catch (\Exception $e) {
			return new NotFoundResponse();
		}
	}

	/**
	 * Marks the notification about a comment as processed
	 * @param IComment $comment
	 * @param IUser $currentUser
	 */
	protected function markProcessed(IComment $comment, IUser $currentUser) {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp('comments')
			->setObject('comment', $comment->getId())
			->setSubject('mention')
			->setUser($currentUser->getUID());
		$this->notificationManager->markProcessed($notification);
	}
}
