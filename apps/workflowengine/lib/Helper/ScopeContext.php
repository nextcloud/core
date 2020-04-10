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

namespace OCA\WorkflowEngine\Helper;

use OCP\WorkflowEngine\IManager;

class ScopeContext {
	/** @var int */
	private $scope;
	/** @var string */
	private $scopeId;
	/** @var string */
	private $hash;

	public function __construct(int $scope, string $scopeId = null) {
		$this->scope = $this->evaluateScope($scope);
		$this->scopeId = $this->evaluateScopeId($scopeId);
	}

	private function evaluateScope(int $scope): int {
		if(in_array($scope, [IManager::SCOPE_ADMIN, IManager::SCOPE_USER], true)) {
			return $scope;
		}
		throw new \InvalidArgumentException('Invalid scope');
	}

	private function evaluateScopeId(string $scopeId = null): string {
		if($this->scope === IManager::SCOPE_USER
			&& trim((string)$scopeId) === '')
		{
			throw new \InvalidArgumentException('user scope requires a user id');
		}
		return trim((string)$scopeId);
	}

	/**
	 * @return int
	 */
	public function getScope(): int {
		return $this->scope;
	}

	/**
	 * @return string
	 */
	public function getScopeId(): string {
		return $this->scopeId;
	}

	public function getHash(): string {
		if($this->hash === null) {
			$this->hash = \hash('sha256', $this->getScope() . '::' . $this->getScopeId());
		}
		return $this->hash;
	}
}
