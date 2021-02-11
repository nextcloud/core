<?php
/**
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OC\Files\Template;

use OC\AppFramework\Bootstrap\Coordinator;
use OC\Files\Cache\Scanner;
use OC\Files\Filesystem;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Template\FileCreatedFromTemplateEvent;
use OCP\Files\Template\ICustomTemplateProvider;
use OCP\Files\Template\ITemplateManager;
use OCP\Files\Template\Template;
use OCP\Files\Template\TemplateFileCreator;
use OCP\IConfig;
use OCP\IPreview;
use OCP\IServerContainer;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class TemplateManager implements ITemplateManager {
	private $registeredTypes = [];
	private $types = [];

	/** @var array|null */
	private $providers = null;

	private $serverContainer;
	private $eventDispatcher;
	private $rootFolder;
	private $userManager;
	private $previewManager;
	private $config;
	private $l10n;
	private $logger;
	private $userId;
	private $l10nFactory;
	/** @var Coordinator */
	private $bootstrapCoordinator;

	public function __construct(
		IServerContainer $serverContainer,
		IEventDispatcher $eventDispatcher,
		Coordinator $coordinator,
		IRootFolder $rootFolder,
		IUserSession $userSession,
		IUserManager $userManager,
		IPreview $previewManager,
		IConfig $config,
		IFactory $l10nFactory,
		LoggerInterface $logger
	) {
		$this->serverContainer = $serverContainer;
		$this->eventDispatcher = $eventDispatcher;
		$this->bootstrapCoordinator = $coordinator;
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->previewManager = $previewManager;
		$this->config = $config;
		$this->l10nFactory = $l10nFactory;
		$this->l10n = $l10nFactory->get('lib');
		$this->logger = $logger;
		$user = $userSession->getUser();
		$this->userId = $user ? $user->getUID() : null;
	}

	public function registerTemplateFileCreator(callable $callback): void {
		$this->registeredTypes[] = $callback;
	}

	public function getRegisteredProviders(): array {
		if ($this->providers !== null) {
			return $this->providers;
		}

		$context = $this->bootstrapCoordinator->getRegistrationContext();

		$this->providers = [];
		foreach ($context->getTemplateProviders() as $provider) {
			$class = $provider->getService();
			$this->providers[$class] = $this->serverContainer->get($class);
		}
		return $this->providers;
	}

	public function getTypes(): array {
		if (!empty($this->types)) {
			return $this->types;
		}
		foreach ($this->registeredTypes as $registeredType) {
			$this->types[] = $registeredType();
		}
		return $this->types;
	}

	public function listCreators(): array {
		$types = $this->getTypes();
		usort($types, function (TemplateFileCreator $a, TemplateFileCreator $b) {
			return $a->getOrder() - $b->getOrder();
		});
		return $types;
	}

	public function listTemplates(): array {
		return array_map(function (TemplateFileCreator $entry) {
			return array_merge($entry->jsonSerialize(), [
				'templates' => $this->getTemplateFiles($entry)
			]);
		}, $this->listCreators());
	}

	/**
	 * @param string $filePath
	 * @param string $templateId
	 * @return array
	 * @throws GenericFileException
	 */
	public function createFromTemplate(string $filePath, string $templateId = '', string $templateType = 'user'): array {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		try {
			$userFolder->get($filePath);
			throw new GenericFileException($this->l10n->t('File already exists'));
		} catch (NotFoundException $e) {
		}
		try {
			$targetFile = $userFolder->newFile($filePath);
			if ($templateType === 'user' && $templateId !== '') {
				$template = $userFolder->get($templateId);
				$template->copy($targetFile->getPath());
			} else {
				$matchingProvider = array_filter($this->getRegisteredProviders(), function (ICustomTemplateProvider $provider) use ($templateType) {
					return $templateType === get_class($provider);
				});
				$provider = array_shift($matchingProvider);
				if ($provider) {
					$template = $provider->getCustomTemplate($templateId);
					$template->copy($targetFile->getPath());
				}
			}
			$this->eventDispatcher->dispatchTyped(new FileCreatedFromTemplateEvent($template, $targetFile));
			return $this->formatFile($userFolder->get($filePath));
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			throw new GenericFileException($this->l10n->t('Failed to create file from template'));
		}
	}

	/**
	 * @return Folder
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	private function getTemplateFolder(): Node {
		if ($this->getTemplatePath() !== '') {
			return $this->rootFolder->getUserFolder($this->userId)->get($this->getTemplatePath());
		}
		throw new NotFoundException();
	}

	private function getTemplateFiles(TemplateFileCreator $type): array {
		$templates = [];
		foreach ($this->getRegisteredProviders() as $provider) {
			foreach ($type->getMimetypes() as $mimetype) {
				foreach ($provider->getCustomTemplates($mimetype) as $template) {
					$templates[] = $template;
				}
			}
		}
		try {
			$userTemplateFolder = $this->getTemplateFolder();
		} catch (\Exception $e) {
			return $templates;
		}
		foreach ($type->getMimetypes() as $mimetype) {
			foreach ($userTemplateFolder->searchByMime($mimetype) as $templateFile) {
				$template = new Template(
					'user',
					$this->rootFolder->getUserFolder($this->userId)->getRelativePath($templateFile->getPath()),
					$templateFile
				);
				$template->setHasPreview($this->previewManager->isAvailable($templateFile));
				$templates[] = $template;
			}
		}

		return $templates;
	}

	/**
	 * @param Node|File $file
	 * @return array
	 * @throws NotFoundException
	 * @throws \OCP\Files\InvalidPathException
	 */
	private function formatFile(Node $file): array {
		return [
			'basename' => $file->getName(),
			'etag' => $file->getEtag(),
			'fileid' => $file->getId(),
			'filename' => $this->rootFolder->getUserFolder($this->userId)->getRelativePath($file->getPath()),
			'lastmod' => $file->getMTime(),
			'mime' => $file->getMimetype(),
			'size' => $file->getSize(),
			'type' => $file->getType(),
			'hasPreview' => $this->previewManager->isAvailable($file)
		];
	}

	public function hasTemplateDirectory(): bool {
		try {
			$this->getTemplateFolder();
			return true;
		} catch (\Exception $e) {
		}
		return false;
	}

	public function setTemplatePath(string $path): void {
		$this->config->setUserValue($this->userId, 'core', 'templateDirectory', $path);
	}

	public function getTemplatePath(): string {
		return $this->config->getUserValue($this->userId, 'core', 'templateDirectory', '');
	}

	public function initializeTemplateDirectory(string $path = null, string $userId = null, $copyTemplates = true): string {
		if ($userId !== null) {
			$this->userId = $userId;
		}

		$defaultSkeletonDirectory = \OC::$SERVERROOT . '/core/skeleton';
		$defaultTemplateDirectory = \OC::$SERVERROOT . '/core/skeleton/Templates';
		$skeletonPath = $this->config->getSystemValue('skeletondirectory', $defaultSkeletonDirectory);
		$skeletonTemplatePath = $this->config->getSystemValue('templatedirectory', $defaultTemplateDirectory);
		$isDefaultSkeleton = $skeletonPath === $defaultSkeletonDirectory;
		$isDefaultTemplates = $skeletonTemplatePath === $defaultTemplateDirectory;
		$userLang = $this->l10nFactory->getUserLanguage($this->userManager->get($this->userId));

		try {
			$l10n = $this->l10nFactory->get('lib', $userLang);
			$userFolder = $this->rootFolder->getUserFolder($this->userId);
			$userTemplatePath = $path ?? $l10n->t('Templates') . '/';

			// Initial user setup without a provided path
			if ($path === null) {
				// All locations are default so we just need to rename the directory to the users language
				if ($isDefaultSkeleton && $isDefaultTemplates) {
					if (!$userFolder->nodeExists('Templates')) {
						return '';
					}
					$newPath = Filesystem::normalizePath($userFolder->getPath() . '/' . $userTemplatePath);
					if ($newPath !== $userFolder->get('Templates')->getPath()) {
						$userFolder->get('Templates')->move($newPath);
					}
					$this->setTemplatePath($userTemplatePath);
					return $userTemplatePath;
				}

				if ($isDefaultSkeleton && !empty($skeletonTemplatePath) && !$isDefaultTemplates && $userFolder->nodeExists('Templates')) {
					$shippedSkeletonTemplates = $userFolder->get('Templates');
					$shippedSkeletonTemplates->delete();
				}
			}

			try {
				$folder = $userFolder->newFolder($userTemplatePath);
			} catch (NotPermittedException $e) {
				$folder = $userFolder->get($userTemplatePath);
			}

			$folderIsEmpty = count($folder->getDirectoryListing()) === 0;

			if (!$copyTemplates) {
				$this->setTemplatePath($userTemplatePath);
				return $userTemplatePath;
			}

			if (!$isDefaultTemplates && $folderIsEmpty) {
				$localizedSkeletonTemplatePath = $this->getLocalizedTemplatePath($skeletonTemplatePath, $userLang);
				if (!empty($localizedSkeletonTemplatePath) && file_exists($localizedSkeletonTemplatePath)) {
					\OC_Util::copyr($localizedSkeletonTemplatePath, $folder);
					$userFolder->getStorage()->getScanner()->scan($folder->getInternalPath(), Scanner::SCAN_RECURSIVE);
					$this->setTemplatePath($userTemplatePath);
					return $userTemplatePath;
				}
			}

			if ($path !== null && $isDefaultSkeleton && $isDefaultTemplates && $folderIsEmpty) {
				$localizedSkeletonPath = $this->getLocalizedTemplatePath($skeletonPath . '/Templates', $userLang);
				if (!empty($localizedSkeletonPath) && file_exists($localizedSkeletonPath)) {
					\OC_Util::copyr($localizedSkeletonPath, $folder);
					$userFolder->getStorage()->getScanner()->scan($folder->getInternalPath(), Scanner::SCAN_RECURSIVE);
					$this->setTemplatePath($userTemplatePath);
					return $userTemplatePath;
				}
			}

			$this->setTemplatePath($path ?? '');
			return $this->getTemplatePath();
		} catch (\Throwable $e) {
			$this->logger->error('Failed to initialize templates directory to user language ' . $userLang . ' for ' . $userId, ['app' => 'files_templates', 'exception' => $e]);
		}
		$this->setTemplatePath('');
		return $this->getTemplatePath();
	}

	private function getLocalizedTemplatePath(string $skeletonTemplatePath, string $userLang) {
		$localizedSkeletonTemplatePath = str_replace('{lang}', $userLang, $skeletonTemplatePath);

		if (!file_exists($localizedSkeletonTemplatePath)) {
			$dialectStart = strpos($userLang, '_');
			if ($dialectStart !== false) {
				$localizedSkeletonTemplatePath = str_replace('{lang}', substr($userLang, 0, $dialectStart), $skeletonTemplatePath);
			}
			if ($dialectStart === false || !file_exists($localizedSkeletonTemplatePath)) {
				$localizedSkeletonTemplatePath = str_replace('{lang}', 'default', $skeletonTemplatePath);
			}
		}

		return $localizedSkeletonTemplatePath;
	}
}
