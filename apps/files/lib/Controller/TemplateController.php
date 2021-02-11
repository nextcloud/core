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

namespace OCA\Files\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCSController;
use OCP\Files\GenericFileException;
use OCP\Files\Template\ITemplateManager;
use OCP\IRequest;

class TemplateController extends OCSController {
	protected $templateManager;

	public function __construct($appName, IRequest $request, ITemplateManager $templateManager) {
		parent::__construct($appName, $request);
		$this->templateManager = $templateManager;
	}

	/**
	 * @NoAdminRequired
	 */
	public function list(): DataResponse {
		return new DataResponse($this->templateManager->listTemplates());
	}

	/**
	 * @NoAdminRequired
	 * @throws OCSForbiddenException
	 */
	public function create(string $filePath, string $templatePath = '', string $templateType = 'user'): DataResponse {
		try {
			return new DataResponse($this->templateManager->createFromTemplate($filePath, $templatePath, $templateType));
		} catch (GenericFileException $e) {
			throw new OCSForbiddenException($e->getMessage());
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function path(string $templatePath = '', bool $copySystemTemplates = false) {
		try {
			$templatePath = $this->templateManager->initializeTemplateDirectory($templatePath, null, $copySystemTemplates);
			return new DataResponse([
				'template_path' => $templatePath,
				'templates' => $this->templateManager->listCreators()
			]);
		} catch (\Exception $e) {
			throw new OCSForbiddenException($e->getMessage());
		}
	}
}
