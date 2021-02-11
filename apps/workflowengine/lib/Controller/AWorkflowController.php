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

use Doctrine\DBAL\Exception;
use OCA\WorkflowEngine\Helper\ScopeContext;
use OCA\WorkflowEngine\Manager;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

abstract class AWorkflowController extends OCSController {

	/** @var Manager */
	protected $manager;

	public function __construct(
		$appName,
		IRequest $request,
		Manager $manager
	) {
		parent::__construct($appName, $request);

		$this->manager = $manager;
	}

	/**
	 * @throws OCSForbiddenException
	 */
	abstract protected function getScopeContext(): ScopeContext;

	/**
	 * Example: curl -u joann -H "OCS-APIREQUEST: true" "http://my.nc.srvr/ocs/v2.php/apps/workflowengine/api/v1/workflows/global?format=json"
	 *
	 * @throws OCSForbiddenException
	 */
	public function index(): DataResponse {
		$operationsByClass = $this->manager->getAllOperations($this->getScopeContext());

		foreach ($operationsByClass as &$operations) {
			foreach ($operations as &$operation) {
				$operation = $this->manager->formatOperation($operation);
			}
		}

		return new DataResponse($operationsByClass);
	}

	/**
	 * Example: curl -u joann -H "OCS-APIREQUEST: true" "http://my.nc.srvr/ocs/v2.php/apps/workflowengine/api/v1/workflows/global/OCA\\Workflow_DocToPdf\\Operation?format=json"
	 *
	 * @throws OCSForbiddenException
	 */
	public function show(string $id): DataResponse {
		$context = $this->getScopeContext();

		// The ID corresponds to a class name
		$operations = $this->manager->getOperations($id, $context);

		foreach ($operations as &$operation) {
			$operation = $this->manager->formatOperation($operation);
		}

		return new DataResponse($operations);
	}

	/**
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 * @throws OCSException
	 */
	public function create(
		string $class,
		string $name,
		array $checks,
		string $operation,
		string $entity,
		array $events
	): DataResponse {
		$context = $this->getScopeContext();
		try {
			$operation = $this->manager->addOperation($class, $name, $checks, $operation, $context, $entity, $events);
			$operation = $this->manager->formatOperation($operation);
			return new DataResponse($operation);
		} catch (\UnexpectedValueException $e) {
			throw new OCSBadRequestException($e->getMessage(), $e);
		} catch (\DomainException $e) {
			throw new OCSForbiddenException($e->getMessage(), $e);
		} catch (Exception $e) {
			throw new OCSException('An internal error occurred', $e->getCode(), $e);
		}
	}

	/**
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 * @throws OCSException
	 */
	public function update(
		int $id,
		string $name,
		array $checks,
		string $operation,
		string $entity,
		array $events
	): DataResponse {
		try {
			$context = $this->getScopeContext();
			$operation = $this->manager->updateOperation($id, $name, $checks, $operation, $context, $entity, $events);
			$operation = $this->manager->formatOperation($operation);
			return new DataResponse($operation);
		} catch (\UnexpectedValueException $e) {
			throw new OCSBadRequestException($e->getMessage(), $e);
		} catch (\DomainException $e) {
			throw new OCSForbiddenException($e->getMessage(), $e);
		} catch (Exception $e) {
			throw new OCSException('An internal error occurred', $e->getCode(), $e);
		}
	}

	/**
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 * @throws OCSException
	 */
	public function destroy(int $id): DataResponse {
		try {
			$deleted = $this->manager->deleteOperation($id, $this->getScopeContext());
			return new DataResponse($deleted);
		} catch (\UnexpectedValueException $e) {
			throw new OCSBadRequestException($e->getMessage(), $e);
		} catch (\DomainException $e) {
			throw new OCSForbiddenException($e->getMessage(), $e);
		} catch (Exception $e) {
			throw new OCSException('An internal error occurred', $e->getCode(), $e);
		}
	}
}
