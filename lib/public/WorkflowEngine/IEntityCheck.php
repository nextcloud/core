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

/**
 * Interface IFileCheck
 *
 * @since 18.0.0
 */
interface IEntityCheck {
	/**
	 * Equips the check with a subject fitting the Entity. For instance, an
	 * entity of File will receive an instance of OCP\Files\Node, or a comment
	 * entity might get an IComment.
	 *
	 * The implementing check must be aware of the incoming type.
	 *
	 * If an unsupported subject is passed the implementation MAY throw an
	 * \UnexpectedValueException.
	 *
	 * @param IEntity $entity
	 * @param mixed $subject
	 * @throws \UnexpectedValueException
	 * @since 18.0.0
	 */
	public function setEntitySubject(IEntity $entity, $subject): void;
}
