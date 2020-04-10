<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCA\DAV\Controller;

use OCA\DAV\Db\Direct;
use OCA\DAV\Db\DirectMapper;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;

class DirectController extends OCSController {

	/** @var IRootFolder */
	private $rootFolder;

	/** @var string */
	private $userId;

	/** @var DirectMapper */
	private $mapper;

	/** @var ISecureRandom */
	private $random;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var IURLGenerator */
	private $urlGenerator;


	public function __construct(string $appName,
								IRequest $request,
								IRootFolder $rootFolder,
								string $userId,
								DirectMapper $mapper,
								ISecureRandom $random,
								ITimeFactory $timeFactory,
								IURLGenerator $urlGenerator) {
		parent::__construct($appName, $request);

		$this->rootFolder = $rootFolder;
		$this->userId = $userId;
		$this->mapper = $mapper;
		$this->random = $random;
		$this->timeFactory = $timeFactory;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getUrl(int $fileId): DataResponse {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);

		$files = $userFolder->getById($fileId);

		if ($files === []) {
			throw new OCSNotFoundException();
		}

		$file = array_shift($files);
		if (!($file instanceof File)) {
			throw new OCSBadRequestException('Direct download only works for files');
		}

		//TODO: at some point we should use the directdownlaod function of storages
		$direct = new Direct();
		$direct->setUserId($this->userId);
		$direct->setFileId($fileId);

		$token = $this->random->generate(60, ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS);
		$direct->setToken($token);
		$direct->setExpiration($this->timeFactory->getTime() + 60 * 60 * 8);

		$this->mapper->insert($direct);

		$url = $this->urlGenerator->getAbsoluteURL('remote.php/direct/'.$token);

		return new DataResponse([
			'url' => $url,
		]);
	}
}
