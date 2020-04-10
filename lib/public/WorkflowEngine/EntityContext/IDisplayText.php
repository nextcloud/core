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

namespace OCP\WorkflowEngine\EntityContext;

/**
 * Interface IDisplayText
 *
 * @package OCP\WorkflowEngine\EntityContext
 *
 * @since 18.0.0
 */
interface IDisplayText {

	/**
	 * returns translated text used for display to the end user. For instance,
	 * it can describe the event in a human readable way.
	 *
	 * The entity may react to a verbosity level that is provided. With the
	 * basic level, 0, it would return brief information, and more with higher
	 * numbers. All information shall be shown at a level of 3.
	 *
	 * @since 18.0.0
	 */
	public function getDisplayText(int $verbosity = 0): string;
}
