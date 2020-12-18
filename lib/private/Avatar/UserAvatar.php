<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Michael Weimann <mail@michael-weimann.eu>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Michael Weimann <mail@michael-weimann.eu>
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

namespace OC\Avatar;

use OC\NotSquareException;
use OC\User\User;
use OC_Image;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use OCP\IImage;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/**
 * This class represents a registered user's avatar.
 */
class UserAvatar extends Avatar {
	/** @var IConfig */
	private $config;

	/** @var ISimpleFolder */
	private $folder;

	/** @var IL10N */
	private $l;

	/** @var User */
	private $user;

	/**
	 * UserAvatar constructor.
	 *
	 * @param IConfig $config The configuration
	 * @param ISimpleFolder $folder The avatar files folder
	 * @param IL10N $l The localization helper
	 * @param User $user The user this class manages the avatar for
	 * @param LoggerInterface $logger The logger
	 */
	public function __construct(
		ISimpleFolder $folder,
		IL10N $l,
		$user,
		LoggerInterface $logger,
		IConfig $config) {
		parent::__construct($logger);
		$this->folder = $folder;
		$this->l = $l;
		$this->user = $user;
		$this->config = $config;
	}

	public function getUser(): User {
		return $this->user;
	}

	/**
	 * Check if an avatar exists for the user
	 *
	 * @return bool
	 */
	public function exists() {
		return $this->folder->fileExists('avatar.jpg') || $this->folder->fileExists('avatar.png');
	}

	/**
	 * Sets the users avatar.
	 *
	 * @param IImage|resource|string $data An image object, imagedata or path to set a new avatar
	 * @throws \Exception if the provided file is not a jpg or png image
	 * @throws \Exception if the provided image is not valid
	 * @throws NotSquareException if the image is not square
	 * @return void
	 */
	public function set($data) {
		$img = $this->getAvatarImage($data);
		$data = $img->data();

		$this->validateAvatar($img);

		$this->remove(true);
		$type = $this->getAvatarImageType($img);
		$file = $this->folder->newFile('avatar.' . $type);
		$file->putContent($data);

		try {
			$generated = $this->folder->getFile('generated');
			$generated->delete();
		} catch (NotFoundException $e) {
			//
		}

		$this->config->setUserValue($this->user->getUID(), 'avatar', 'generated', 'false');
		$this->user->triggerChange('avatar', $file);
	}

	/**
	 * Returns an image from several sources.
	 *
	 * @param IImage|resource|string $data An image object, imagedata or path to the avatar
	 * @return IImage
	 */
	private function getAvatarImage($data) {
		if ($data instanceof IImage) {
			return $data;
		}

		$img = new OC_Image();
		if (is_resource($data) && get_resource_type($data) === 'gd') {
			$img->setResource($data);
		} elseif (is_resource($data)) {
			$img->loadFromFileHandle($data);
		} else {
			try {
				// detect if it is a path or maybe the images as string
				$result = @realpath($data);
				if ($result === false || $result === null) {
					$img->loadFromData($data);
				} else {
					$img->loadFromFile($data);
				}
			} catch (\Error $e) {
				$img->loadFromData($data);
			}
		}

		return $img;
	}

	/**
	 * Returns the avatar image type.
	 *
	 * @param IImage $avatar
	 * @return string
	 */
	private function getAvatarImageType(IImage $avatar) {
		$type = substr($avatar->mimeType(), -3);
		if ($type === 'peg') {
			$type = 'jpg';
		}
		return $type;
	}

	/**
	 * Validates an avatar image:
	 * - must be "png" or "jpg"
	 * - must be "valid"
	 * - must be in square format
	 *
	 * @param IImage $avatar The avatar to validate
	 * @throws \Exception if the provided file is not a jpg or png image
	 * @throws \Exception if the provided image is not valid
	 * @throws NotSquareException if the image is not square
	 */
	private function validateAvatar(IImage $avatar) {
		$type = $this->getAvatarImageType($avatar);

		if ($type !== 'jpg' && $type !== 'png') {
			throw new \Exception($this->l->t('Unknown filetype'));
		}

		if (!$avatar->valid()) {
			throw new \Exception($this->l->t('Invalid image'));
		}

		if (!($avatar->height() === $avatar->width())) {
			throw new NotSquareException($this->l->t('Avatar image is not square'));
		}
	}

	/**
	 * Removes the users avatar.
	 * @return void
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function remove(bool $silent = false) {
		$avatars = $this->folder->getDirectoryListing();

		$this->config->setUserValue($this->user->getUID(), 'avatar', 'version',
			(int) $this->config->getUserValue($this->user->getUID(), 'avatar', 'version', 0) + 1);

		foreach ($avatars as $avatar) {
			$avatar->delete();
		}
		$this->config->setUserValue($this->user->getUID(), 'avatar', 'generated', 'true');
		if (!$silent) {
			$this->user->triggerChange('avatar', '');
		}
	}

	/**
	 * Get the extension of the avatar. If there is no avatar throw Exception
	 *
	 * @return string
	 * @throws NotFoundException
	 */
	private function getExtension() {
		if ($this->folder->fileExists('avatar.jpg')) {
			return 'jpg';
		} elseif ($this->folder->fileExists('avatar.png')) {
			return 'png';
		}
		throw new NotFoundException;
	}

	/**
	 * Returns the avatar for an user.
	 *
	 * If there is no avatar file yet, one is generated.
	 *
	 * @param int $size
	 * @return ISimpleFile
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function getFile($size) {
		$size = (int) $size;

		try {
			$ext = $this->getExtension();
		} catch (NotFoundException $e) {
			if (!$data = $this->generateAvatarFromSvg(1024)) {
				$data = $this->generateAvatar($this->getDisplayName(), 1024);
			}
			$avatar = $this->folder->newFile('avatar.png');
			$avatar->putContent($data);
			$ext = 'png';

			$this->folder->newFile('generated', '');
			$this->config->setUserValue($this->user->getUID(), 'avatar', 'generated', 'true');
		}

		if ($size === -1) {
			$path = 'avatar.' . $ext;
		} else {
			$path = 'avatar.' . $size . '.' . $ext;
		}

		try {
			$file = $this->folder->getFile($path);
		} catch (NotFoundException $e) {
			if ($size <= 0) {
				throw new NotFoundException;
			}

			if ($this->folder->fileExists('generated')) {
				if (!$data = $this->generateAvatarFromSvg($size)) {
					$data = $this->generateAvatar($this->getDisplayName(), $size);
				}
			} else {
				$avatar = new OC_Image();
				$file = $this->folder->getFile('avatar.' . $ext);
				$avatar->loadFromData($file->getContent());
				$avatar->resize($size);
				$data = $avatar->data();
			}

			try {
				$file = $this->folder->newFile($path);
				$file->putContent($data);
			} catch (NotPermittedException $e) {
				$this->logger->error('Failed to save avatar for ' . $this->user->getUID());
				throw new NotFoundException();
			}
		}

		if ($this->config->getUserValue($this->user->getUID(), 'avatar', 'generated', null) === null) {
			$generated = $this->folder->fileExists('generated') ? 'true' : 'false';
			$this->config->setUserValue($this->user->getUID(), 'avatar', 'generated', $generated);
		}

		return $file;
	}

	/**
	 * Returns the user display name.
	 *
	 * @return string
	 */
	public function getDisplayName(): string {
		return $this->user->getDisplayName();
	}

	/**
	 * Handles user changes.
	 *
	 * @param string $feature The changed feature
	 * @param mixed $oldValue The previous value
	 * @param mixed $newValue The new value
	 * @throws NotPermittedException
	 * @throws \OCP\PreConditionNotMetException
	 */
	public function userChanged($feature, $oldValue, $newValue) {
		// If the avatar is not generated (so an uploaded image) we skip this
		if (!$this->folder->fileExists('generated')) {
			return;
		}

		$this->remove();
	}

	/**
	 * Check if the avatar of a user is a custom uploaded one
	 *
	 * @return bool
	 */
	public function isCustomAvatar(): bool {
		return $this->config->getUserValue($this->user->getUID(), 'avatar', 'generated', 'false') !== 'true';
	}
}
