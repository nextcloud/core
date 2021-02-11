<?php
/**
 * @copyright 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
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
namespace OC\Repair\Owncloud;

use OC\BackgroundJob\QueuedJob;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\Storage;
use OCP\IAvatarManager;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use function is_resource;

class MoveAvatarsBackgroundJob extends QueuedJob {

	/** @var IUserManager */
	private $userManager;

	/** @var LoggerInterface */
	private $logger;

	/** @var IAvatarManager */
	private $avatarManager;

	/** @var Storage */
	private $owncloudAvatarStorage;

	public function __construct(IUserManager $userManager, LoggerInterface $logger, IAvatarManager $avatarManager, IRootFolder $rootFolder) {
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->avatarManager = $avatarManager;
		try {
			$this->owncloudAvatarStorage = $rootFolder->get('avatars')->getStorage();
		} catch (\Exception $e) {
		}
	}

	public function run($arguments) {
		$this->logger->info('Started migrating avatars to AppData folder');
		$this->moveAvatars();
		$this->logger->info('All avatars migrated to AppData folder');
	}

	private function moveAvatars(): void {
		if (!$this->owncloudAvatarStorage) {
			$this->logger->info('No legacy avatars available, skipping migration');
			return;
		}

		$counter = 0;
		$this->userManager->callForSeenUsers(function (IUser $user) use ($counter) {
			$uid = $user->getUID();

			$path = 'avatars/' . $this->buildOwnCloudAvatarPath($uid);
			$avatar = $this->avatarManager->getAvatar($uid);
			try {
				$avatarPath = $path . '/avatar.' . $this->getExtension($path);
				$resource = $this->owncloudAvatarStorage->fopen($avatarPath, 'r');
				if (is_resource($resource)) {
					$avatar->set($resource);
					fclose($resource);
				} else {
					throw new \Exception('Failed to open old avatar file for reading');
				}
			} catch (NotFoundException $e) {
				// In case there is no avatar we can just skip
			} catch (\Throwable $e) {
				$this->logger->error('Failed to migrate avatar for user ' . $uid, ['exception' => $e]);
			}

			$counter++;
			if ($counter % 100 === 0) {
				$this->logger->info('{amount} avatars migrated', ['amount' => $counter]);
			}
		});
	}

	/**
	 * @throws NotFoundException
	 */
	private function getExtension(string $path): string {
		if ($this->owncloudAvatarStorage->file_exists("{$path}/avatar.jpg")) {
			return 'jpg';
		}
		if ($this->owncloudAvatarStorage->file_exists("{$path}/avatar.png")) {
			return 'png';
		}
		throw new NotFoundException("{$path}/avatar.jpg|png");
	}

	protected function buildOwnCloudAvatarPath(string $userId): string {
		return substr_replace(substr_replace(md5($userId), '/', 4, 0), '/', 2, 0);
	}
}
