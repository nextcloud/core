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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\WorkflowEngine;

use RuntimeException;

/**
 * Class IRuleMatcher
 *
 * @package OCP\WorkflowEngine
 *
 * @since 18.0.0
 */
interface IRuleMatcher extends IFileCheck {
	/**
	 * This method is left for backwards compatibility and easier porting of
	 * apps. Please use 'getFlows' instead (and setOperation if you implement
	 * an IComplexOperation).
	 *
	 * @since 18.0.0
	 * @deprecated 18.0.0
	 */
	public function getMatchingOperations(string $class, bool $returnFirstMatchingOperationOnly = true): array;

	/**
	 * @throws RuntimeException
	 * @since 18.0.0
	 */
	public function getFlows(bool $returnFirstMatchingOperationOnly = true): array;

	/**
	 * this method can only be called once and is typically called by the
	 * Flow engine, unless for IComplexOperations.
	 *
	 * @throws RuntimeException
	 * @since 18.0.0
	 */
	public function setOperation(IOperation $operation): void;

	/**
	 * this method can only be called once and is typically called by the
	 * Flow engine, unless for IComplexOperations.
	 *
	 * @throws RuntimeException
	 * @since 18.0.0
	 */
	public function setEntity(IEntity $entity): void;

	/**
	 * returns the entity which might provide more information, depending on
	 * the interfaces it implements
	 *
	 * @return IEntity
	 * @since 18.0.0
	 */
	public function getEntity(): IEntity;
}
