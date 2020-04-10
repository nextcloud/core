<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\WorkflowEngine\Controller;

use OCA\WorkflowEngine\Helper\ScopeContext;
use OCA\WorkflowEngine\Manager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\WorkflowEngine\IManager;

class UserWorkflowsController extends AWorkflowController {

	/** @var IUserSession */
	private $session;

	/** @var ScopeContext */
	private $scopeContext;

	public function __construct(
		$appName,
		IRequest $request,
		Manager $manager,
		IUserSession $session
	) {
		parent::__construct($appName, $request, $manager);

		$this->session = $session;
	}

	/**
	 * Retrieve all configured workflow rules
	 *
	 * Example: curl -u joann -H "OCS-APIREQUEST: true" "http://my.nc.srvr/ocs/v2.php/apps/workflowengine/api/v1/workflows/user?format=json"
	 *
	 * @NoAdminRequired
	 * @throws OCSForbiddenException
	 */
	public function index(): DataResponse {
		return parent::index();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Example: curl -u joann -H "OCS-APIREQUEST: true" "http://my.nc.srvr/ocs/v2.php/apps/workflowengine/api/v1/workflows/user/OCA\\Workflow_DocToPdf\\Operation?format=json"
	 * @throws OCSForbiddenException
	 */
	public function show(string $id): DataResponse {
		return parent::show($id);
	}

	/**
	 * @NoAdminRequired
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function create(string $class, string $name, array $checks, string $operation, string $entity, array $events): DataResponse {
		return parent::create($class, $name, $checks, $operation, $entity, $events);
	}

	/**
	 * @NoAdminRequired
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function update(int $id, string $name, array $checks, string $operation, string $entity, array $events): DataResponse {
		return parent::update($id, $name, $checks, $operation, $entity, $events);
	}

	/**
	 * @NoAdminRequired
	 * @throws OCSForbiddenException
	 */
	public function destroy(int $id): DataResponse {
		return parent::destroy($id);
	}

	/**
	 * @throws OCSForbiddenException
	 */
	protected function getScopeContext(): ScopeContext {
		if($this->scopeContext === null) {
			$user = $this->session->getUser();
			if(!$user || !$this->manager->isUserScopeEnabled()) {
				throw new OCSForbiddenException('User not logged in');
			}
			$this->scopeContext = new ScopeContext(IManager::SCOPE_USER, $user->getUID());
		}
		return $this->scopeContext;
	}

}
