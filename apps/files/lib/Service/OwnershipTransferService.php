<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sascha Wiswedel <sascha.wiswedel@nextcloud.com>
 * @author Tobia De Koninck <LEDfan@users.noreply.github.com>
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

namespace OCA\Files\Service;

use Closure;
use OC\Files\Filesystem;
use OC\Files\View;
use OCA\Files\Exception\TransferOwnershipException;
use OCP\Encryption\IManager as IEncryptionManager;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\FileInfo;
use OCP\Files\IHomeStorage;
use OCP\Files\InvalidPathException;
use OCP\Files\Mount\IMountManager;
use OCP\IUser;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use function array_merge;
use function basename;
use function count;
use function date;
use function is_dir;
use function rtrim;

class OwnershipTransferService {

	/** @var IEncryptionManager */
	private $encryptionManager;

	/** @var IShareManager */
	private $shareManager;

	/** @var IMountManager */
	private $mountManager;

	/** @var IUserMountCache */
	private $userMountCache;

	public function __construct(IEncryptionManager $manager,
								IShareManager $shareManager,
								IMountManager $mountManager,
								IUserMountCache $userMountCache) {
		$this->encryptionManager = $manager;
		$this->shareManager = $shareManager;
		$this->mountManager = $mountManager;
		$this->userMountCache = $userMountCache;
	}

	/**
	 * @param IUser $sourceUser
	 * @param IUser $destinationUser
	 * @param string $path
	 *
	 * @param OutputInterface|null $output
	 * @param bool $move
	 * @throws TransferOwnershipException
	 * @throws \OC\User\NoUserException
	 */
	public function transfer(IUser $sourceUser,
							 IUser $destinationUser,
							 string $path,
							 ?OutputInterface $output = null,
							 bool $move = false,
							 bool $firstLogin = false): void {
		$output = $output ?? new NullOutput();
		$sourceUid = $sourceUser->getUID();
		$destinationUid = $destinationUser->getUID();
		$sourcePath = rtrim($sourceUid . '/files/' . $path, '/');

		// target user has to be ready
		if ($destinationUser->getLastLogin() === 0 || !$this->encryptionManager->isReadyForUser($destinationUid)) {
			throw new TransferOwnershipException("The target user is not ready to accept files. The user has at least to have logged in once.", 2);
		}

		// setup filesystem
		Filesystem::initMountPoints($sourceUid);
		Filesystem::initMountPoints($destinationUid);

		$view = new View();

		if ($move) {
			$finalTarget = "$destinationUid/files/";
		} else {
			$date = date('Y-m-d H-i-s');

			// Remove some characters which are prone to cause errors
			$cleanUserName = str_replace(['\\', '/', ':', '.', '?', '#', '\'', '"'], '-', $sourceUser->getDisplayName());
			// Replace multiple dashes with one dash
			$cleanUserName = preg_replace('/-{2,}/s', '-', $cleanUserName);
			$cleanUserName = $cleanUserName ?: $sourceUid;

			$finalTarget = "$destinationUid/files/transferred from $cleanUserName on $date";
			try {
				$view->verifyPath(dirname($finalTarget), basename($finalTarget));
			} catch (InvalidPathException $e) {
				$finalTarget = "$destinationUid/files/transferred from $sourceUid on $date";
			}
		}

		if (!($view->is_dir($sourcePath) || $view->is_file($sourcePath))) {
			throw new TransferOwnershipException("Unknown path provided: $path", 1);
		}

		if ($move && (
				!$view->is_dir($finalTarget) || (
					!$firstLogin &&
					count($view->getDirectoryContent($finalTarget)) > 0
				)
			)
		) {
			throw new TransferOwnershipException("Destination path does not exists or is not empty", 1);
		}


		// analyse source folder
		$this->analyse(
			$sourceUid,
			$destinationUid,
			$sourcePath,
			$view,
			$output
		);

		// collect all the shares
		$shares = $this->collectUsersShares(
			$sourceUid,
			$output,
			$view,
			$sourcePath
		);

		// transfer the files
		$this->transferFiles(
			$sourceUid,
			$sourcePath,
			$finalTarget,
			$view,
			$output
		);

		// restore the shares
		$this->restoreShares(
			$sourceUid,
			$destinationUid,
			$shares,
			$output
		);
	}

	private function walkFiles(View $view, $path, Closure $callBack) {
		foreach ($view->getDirectoryContent($path) as $fileInfo) {
			if (!$callBack($fileInfo)) {
				return;
			}
			if ($fileInfo->getType() === FileInfo::TYPE_FOLDER) {
				$this->walkFiles($view, $fileInfo->getPath(), $callBack);
			}
		}
	}

	/**
	 * @param OutputInterface $output
	 *
	 * @throws \Exception
	 */
	protected function analyse(string $sourceUid,
							   string $destinationUid,
							   string $sourcePath,
							   View $view,
							   OutputInterface $output): void {
		$output->writeln('Validating quota');
		$size = $view->getFileInfo($sourcePath, false)->getSize(false);
		$freeSpace = $view->free_space($destinationUid . '/files/');
		if ($size > $freeSpace && $freeSpace !== FileInfo::SPACE_UNKNOWN) {
			$output->writeln('<error>Target user does not have enough free space available.</error>');
			throw new \Exception('Execution terminated.');
		}

		$output->writeln("Analysing files of $sourceUid ...");
		$progress = new ProgressBar($output);
		$progress->start();

		$encryptedFiles = [];
		$this->walkFiles($view, $sourcePath,
			function (FileInfo $fileInfo) use ($progress) {
				if ($fileInfo->getType() === FileInfo::TYPE_FOLDER) {
					// only analyze into folders from main storage,
					if (!$fileInfo->getStorage()->instanceOfStorage(IHomeStorage::class)) {
						return false;
					}
					return true;
				}
				$progress->advance();
				if ($fileInfo->isEncrypted()) {
					$encryptedFiles[] = $fileInfo;
				}
				return true;
			});
		$progress->finish();
		$output->writeln('');

		// no file is allowed to be encrypted
		if (!empty($encryptedFiles)) {
			$output->writeln("<error>Some files are encrypted - please decrypt them first.</error>");
			foreach ($encryptedFiles as $encryptedFile) {
				/** @var FileInfo $encryptedFile */
				$output->writeln("  " . $encryptedFile->getPath());
			}
			throw new \Exception('Execution terminated.');
		}
	}

	private function collectUsersShares(string $sourceUid,
										OutputInterface $output,
										View $view,
										string $path): array {
		$output->writeln("Collecting all share information for files and folders of $sourceUid ...");

		$shares = [];
		$progress = new ProgressBar($output);
		foreach ([IShare::TYPE_GROUP, IShare::TYPE_USER, IShare::TYPE_LINK, IShare::TYPE_REMOTE, IShare::TYPE_ROOM, IShare::TYPE_EMAIL, IShare::TYPE_CIRCLE, IShare::TYPE_DECK] as $shareType) {
			$offset = 0;
			while (true) {
				$sharePage = $this->shareManager->getSharesBy($sourceUid, $shareType, null, true, 50, $offset);
				$progress->advance(count($sharePage));
				if (empty($sharePage)) {
					break;
				}
				if ($path !== "$sourceUid/files") {
					$sharePage = array_filter($sharePage, function (IShare $share) use ($view, $path) {
						try {
							$relativePath = $view->getPath($share->getNodeId());
							$singleFileTranfer = $view->is_file($path);
							if ($singleFileTranfer) {
								return Filesystem::normalizePath($relativePath) === Filesystem::normalizePath($path);
							}

							return mb_strpos(
								Filesystem::normalizePath($relativePath . '/', false),
								Filesystem::normalizePath($path . '/', false)) === 0;
						} catch (\Exception $e) {
							return false;
						}
					});
				}
				$shares = array_merge($shares, $sharePage);
				$offset += 50;
			}
		}

		$progress->finish();
		$output->writeln('');
		return $shares;
	}

	/**
	 * @throws TransferOwnershipException
	 */
	protected function transferFiles(string $sourceUid,
									 string $sourcePath,
									 string $finalTarget,
									 View $view,
									 OutputInterface $output): void {
		$output->writeln("Transferring files to $finalTarget ...");

		// This change will help user to transfer the folder specified using --path option.
		// Else only the content inside folder is transferred which is not correct.
		if ($sourcePath !== "$sourceUid/files") {
			$view->mkdir($finalTarget);
			$finalTarget = $finalTarget . '/' . basename($sourcePath);
		}
		if ($view->rename($sourcePath, $finalTarget) === false) {
			throw new TransferOwnershipException("Could not transfer files.", 1);
		}
		if (!is_dir("$sourceUid/files")) {
			// because the files folder is moved away we need to recreate it
			$view->mkdir("$sourceUid/files");
		}
	}

	private function restoreShares(string $sourceUid,
								   string $destinationUid,
								   array $shares,
								   OutputInterface $output) {
		$output->writeln("Restoring shares ...");
		$progress = new ProgressBar($output, count($shares));

		foreach ($shares as $share) {
			try {
				if ($share->getShareType() === IShare::TYPE_USER &&
					$share->getSharedWith() === $destinationUid) {
					// Unmount the shares before deleting, so we don't try to get the storage later on.
					$shareMountPoint = $this->mountManager->find('/' . $destinationUid . '/files' . $share->getTarget());
					if ($shareMountPoint) {
						$this->mountManager->removeMount($shareMountPoint->getMountPoint());
					}
					$this->shareManager->deleteShare($share);
				} else {
					if ($share->getShareOwner() === $sourceUid) {
						$share->setShareOwner($destinationUid);
					}
					if ($share->getSharedBy() === $sourceUid) {
						$share->setSharedBy($destinationUid);
					}


					// trigger refetching of the node so that the new owner and mountpoint are taken into account
					// otherwise the checks on the share update will fail due to the original node not being available in the new user scope
					$this->userMountCache->clear();
					$share->setNodeId($share->getNode()->getId());

					$this->shareManager->updateShare($share);
				}
			} catch (\OCP\Files\NotFoundException $e) {
				$output->writeln('<error>Share with id ' . $share->getId() . ' points at deleted file, skipping</error>');
			} catch (\Throwable $e) {
				$output->writeln('<error>Could not restore share with id ' . $share->getId() . ':' . $e->getTraceAsString() . '</error>');
			}
			$progress->advance();
		}
		$progress->finish();
		$output->writeln('');
	}
}
