<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace OCA\WorkflowEngine\Check;

use OCA\WorkflowEngine\Entity\File;
use OCP\IL10N;
use OCP\IRequest;
use OCP\WorkflowEngine\IFileCheck;

class FileName extends AbstractStringCheck implements IFileCheck {
	use TFileCheck;

	/** @var IRequest */
	protected $request;

	/**
	 * @param IL10N $l
	 * @param IRequest $request
	 */
	public function __construct(IL10N $l, IRequest $request) {
		parent::__construct($l);
		$this->request = $request;
	}

	/**
	 * @return string
	 */
	protected function getActualValue(): string {
		return $this->path === null ? '' : basename($this->path);
	}

	/**
	 * @param string $operator
	 * @param string $checkValue
	 * @param string $actualValue
	 * @return bool
	 */
	protected function executeStringCheck($operator, $checkValue, $actualValue): bool {
		if ($operator === 'is' || $operator === '!is') {
			$checkValue = mb_strtolower($checkValue);
			$actualValue = mb_strtolower($actualValue);
		}
		return parent::executeStringCheck($operator, $checkValue, $actualValue);
	}

	public function supportedEntities(): array {
		return [ File::class ];
	}

	public function isAvailableForScope(int $scope): bool {
		return true;
	}
}
