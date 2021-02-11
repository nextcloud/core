<?php
/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Tobias Kaminsky <tobias@kaminsky.me>
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

namespace OC\DirectEditing;

use Doctrine\DBAL\FetchMode;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DirectEditing\ACreateFromTemplate;
use OCP\DirectEditing\IEditor;
use \OCP\DirectEditing\IManager;
use OCP\DirectEditing\IToken;
use OCP\Encryption\IManager as EncryptionManager;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Security\ISecureRandom;
use OCP\Share\IShare;
use Throwable;
use function array_key_exists;
use function in_array;

class Manager implements IManager {
	private const TOKEN_CLEANUP_TIME = 12 * 60 * 60 ;

	public const TABLE_TOKENS = 'direct_edit';

	/** @var IEditor[] */
	private $editors = [];
	/** @var IDBConnection */
	private $connection;
	/** @var ISecureRandom */
	private $random;
	/** @var string|null */
	private $userId;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IL10N */
	private $l10n;
	/** @var EncryptionManager */
	private $encryptionManager;

	public function __construct(
		ISecureRandom $random,
		IDBConnection $connection,
		IUserSession $userSession,
		IRootFolder $rootFolder,
		IFactory $l10nFactory,
		EncryptionManager $encryptionManager
	) {
		$this->random = $random;
		$this->connection = $connection;
		$this->userId = $userSession->getUser() ? $userSession->getUser()->getUID() : null;
		$this->rootFolder = $rootFolder;
		$this->l10n = $l10nFactory->get('core');
		$this->encryptionManager = $encryptionManager;
	}

	public function registerDirectEditor(IEditor $directEditor): void {
		$this->editors[$directEditor->getId()] = $directEditor;
	}

	public function getEditors(): array {
		return $this->editors;
	}

	public function getTemplates(string $editor, string $type): array {
		if (!array_key_exists($editor, $this->editors)) {
			throw new \RuntimeException('No matching editor found');
		}
		$templates = [];
		foreach ($this->editors[$editor]->getCreators() as $creator) {
			if ($creator->getId() === $type) {
				$templates = [
					'empty' => [
						'id' => 'empty',
						'title' => $this->l10n->t('Empty file'),
						'preview' => null
					]
				];

				if ($creator instanceof ACreateFromTemplate) {
					$templates = $creator->getTemplates();
				}

				$templates = array_map(function ($template) use ($creator) {
					$template['extension'] = $creator->getExtension();
					$template['mimetype'] = $creator->getMimetype();
					return $template;
				}, $templates);
			}
		}
		$return = [];
		$return['templates'] = $templates;
		return $return;
	}

	public function create(string $path, string $editorId, string $creatorId, $templateId = null): string {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		if ($userFolder->nodeExists($path)) {
			throw new \RuntimeException('File already exists');
		} else {
			$file = $userFolder->newFile($path);
			$editor = $this->getEditor($editorId);
			$creators = $editor->getCreators();
			foreach ($creators as $creator) {
				if ($creator->getId() === $creatorId) {
					$creator->create($file, $creatorId, $templateId);
					return $this->createToken($editorId, $file, $path);
				}
			}
		}

		throw new \RuntimeException('No creator found');
	}

	public function open(string $filePath, string $editorId = null): string {
		/** @var File $file */
		$file = $this->rootFolder->getUserFolder($this->userId)->get($filePath);

		if ($editorId === null) {
			$editorId = $this->findEditorForFile($file);
		}
		if (!array_key_exists($editorId, $this->editors)) {
			throw new \RuntimeException("Editor $editorId is unknown");
		}

		return $this->createToken($editorId, $file, $filePath);
	}

	private function findEditorForFile(File $file) {
		foreach ($this->editors as $editor) {
			if (in_array($file->getMimeType(), $editor->getMimetypes())) {
				return $editor->getId();
			}
		}
		throw new \RuntimeException('No default editor found for files mimetype');
	}

	public function edit(string $token): Response {
		try {
			/** @var IEditor $editor */
			$tokenObject = $this->getToken($token);
			if ($tokenObject->hasBeenAccessed()) {
				throw new \RuntimeException('Token has already been used and can only be used for followup requests');
			}
			$editor = $this->getEditor($tokenObject->getEditor());
			$this->accessToken($token);
		} catch (Throwable $throwable) {
			$this->invalidateToken($token);
			return new NotFoundResponse();
		}
		return $editor->open($tokenObject);
	}

	public function editSecure(File $file, string $editorId): TemplateResponse {
		// TODO: Implementation in follow up
	}

	private function getEditor($editorId): IEditor {
		if (!array_key_exists($editorId, $this->editors)) {
			throw new \RuntimeException('No editor found');
		}
		return $this->editors[$editorId];
	}

	public function getToken(string $token): IToken {
		$query = $this->connection->getQueryBuilder();
		$query->select('*')->from(self::TABLE_TOKENS)
			->where($query->expr()->eq('token', $query->createNamedParameter($token, IQueryBuilder::PARAM_STR)));
		$result = $query->execute();
		if ($tokenRow = $result->fetch(FetchMode::ASSOCIATIVE)) {
			return new Token($this, $tokenRow);
		}
		throw new \RuntimeException('Failed to validate the token');
	}

	public function cleanup(): int {
		$query = $this->connection->getQueryBuilder();
		$query->delete(self::TABLE_TOKENS)
			->where($query->expr()->lt('timestamp', $query->createNamedParameter(time() - self::TOKEN_CLEANUP_TIME)));
		return $query->execute();
	}

	public function refreshToken(string $token): bool {
		$query = $this->connection->getQueryBuilder();
		$query->update(self::TABLE_TOKENS)
			->set('timestamp', $query->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('token', $query->createNamedParameter($token, IQueryBuilder::PARAM_STR)));
		$result = $query->execute();
		return $result !== 0;
	}


	public function invalidateToken(string $token): bool {
		$query = $this->connection->getQueryBuilder();
		$query->delete(self::TABLE_TOKENS)
			->where($query->expr()->eq('token', $query->createNamedParameter($token, IQueryBuilder::PARAM_STR)));
		$result = $query->execute();
		return $result !== 0;
	}

	public function accessToken(string $token): bool {
		$query = $this->connection->getQueryBuilder();
		$query->update(self::TABLE_TOKENS)
			->set('accessed', $query->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->set('timestamp', $query->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
			->where($query->expr()->eq('token', $query->createNamedParameter($token, IQueryBuilder::PARAM_STR)));
		$result = $query->execute();
		return $result !== 0;
	}

	public function invokeTokenScope($userId): void {
		\OC_User::setIncognitoMode(true);
		\OC_User::setUserId($userId);
	}

	public function createToken($editorId, File $file, string $filePath, IShare $share = null): string {
		$token = $this->random->generate(64, ISecureRandom::CHAR_HUMAN_READABLE);
		$query = $this->connection->getQueryBuilder();
		$query->insert(self::TABLE_TOKENS)
			->values([
				'token' => $query->createNamedParameter($token),
				'editor_id' => $query->createNamedParameter($editorId),
				'file_id' => $query->createNamedParameter($file->getId()),
				'file_path' => $query->createNamedParameter($filePath),
				'user_id' => $query->createNamedParameter($this->userId),
				'share_id' => $query->createNamedParameter($share !== null ? $share->getId(): null),
				'timestamp' => $query->createNamedParameter(time())
			]);
		$query->execute();
		return $token;
	}

	/**
	 * @param $userId
	 * @param $fileId
	 * @param null $filePath
	 * @return Node
	 * @throws NotFoundException
	 */
	public function getFileForToken($userId, $fileId, $filePath = null): Node {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		if ($filePath !== null) {
			return $userFolder->get($filePath);
		}
		$files = $userFolder->getById($fileId);
		if (count($files) === 0) {
			throw new NotFoundException('File nound found by id ' . $fileId);
		}
		return $files[0];
	}

	public function isEnabled(): bool {
		if (!$this->encryptionManager->isEnabled()) {
			return true;
		}

		try {
			$moduleId = $this->encryptionManager->getDefaultEncryptionModuleId();
			$module = $this->encryptionManager->getEncryptionModule($moduleId);
			/** @var \OCA\Encryption\Util $util */
			$util = \OC::$server->get(\OCA\Encryption\Util::class);
			if ($module->isReadyForUser($this->userId) && $util->isMasterKeyEnabled()) {
				return true;
			}
		} catch (Throwable $e) {
		}
		return false;
	}
}
