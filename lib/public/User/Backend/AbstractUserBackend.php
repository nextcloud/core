<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Alexey Abel <dev@abelonline.de>
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

namespace OCP\User\Backend;

/**
 * Class AbstractUserBackend should be used as a base for all user back ends, e.g. implemented by apps.
 * Additionally implement any number of interfaces in OCP\User\Backend\Action.
 *
 * @package OCP\User\Backend
 * @since 21.0.0
 */
class AbstractUserBackend {

	/** @var string */
	private $backEndName;

	public function __construct(string $backEndName) {
		$this->backEndName = $backEndName;
	}

	public function getName(): string {
		return $this->backEndName;
	}
}
