<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP\SystemTag;

/**
 * Public interface for a system-wide tag.
 *
 * @since 9.0.0
 */
interface ISystemTag {

	/**
	 * Returns the tag id
	 *
	 * @return string id
	 *
	 * @since 9.0.0
	 */
	public function getId(): string;

	/**
	 * Returns the tag display name
	 *
	 * @return string tag display name
	 *
	 * @since 9.0.0
	 */
	public function getName(): string;

	/**
	 * Returns whether the tag is visible for regular users
	 *
	 * @return bool true if visible, false otherwise
	 *
	 * @since 9.0.0
	 */
	public function isUserVisible(): bool;

	/**
	 * Returns whether the tag can be assigned to objects by regular users
	 *
	 * @return bool true if assignable, false otherwise
	 *
	 * @since 9.0.0
	 */
	public function isUserAssignable(): bool;
}
