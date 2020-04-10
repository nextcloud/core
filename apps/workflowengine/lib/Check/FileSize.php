<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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
use OCP\Util;
use OCP\WorkflowEngine\ICheck;

class FileSize implements ICheck {

	/** @var int */
	protected $size;

	/** @var IL10N */
	protected $l;

	/** @var IRequest */
	protected $request;

	/**
	 * @param IL10N $l
	 * @param IRequest $request
	 */
	public function __construct(IL10N $l, IRequest $request) {
		$this->l = $l;
		$this->request = $request;
	}

	/**
	 * @param string $operator
	 * @param string $value
	 * @return bool
	 */
	public function executeCheck($operator, $value) {
		$size = $this->getFileSizeFromHeader();

		$value = Util::computerFileSize($value);
		if ($size !== false) {
			switch ($operator) {
				case 'less':
					return $size < $value;
				case '!less':
					return $size >= $value;
				case 'greater':
					return $size > $value;
				case '!greater':
					return $size <= $value;
			}
		}
		return false;
	}

	/**
	 * @param string $operator
	 * @param string $value
	 * @throws \UnexpectedValueException
	 */
	public function validateCheck($operator, $value) {
		if (!in_array($operator, ['less', '!less', 'greater', '!greater'])) {
			throw new \UnexpectedValueException($this->l->t('The given operator is invalid'), 1);
		}

		if (!preg_match('/^[0-9]+[ ]?[kmgt]?b$/i', $value)) {
			throw new \UnexpectedValueException($this->l->t('The given file size is invalid'), 2);
		}
	}

	/**
	 * @return string
	 */
	protected function getFileSizeFromHeader() {
		if ($this->size !== null) {
			return $this->size;
		}

		$size = $this->request->getHeader('OC-Total-Length');
		if ($size === '') {
			if (in_array($this->request->getMethod(), ['POST', 'PUT'])) {
				$size = $this->request->getHeader('Content-Length');
			}
		}

		if ($size === '') {
			$size = false;
		}

		$this->size = $size;
		return $this->size;
	}

	public function supportedEntities(): array {
		return [ File::class ];
	}

	public function isAvailableForScope(int $scope): bool {
		return true;
	}
}
