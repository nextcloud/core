<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files\Event;

use OCP\EventDispatcher\Event;

/**
 * This event is triggered when the files app is rendered.
 * It can be used to add additional scripts to the files app.
 *
 * @since 17.0.0
 */
class LoadAdditionalScriptsEvent extends Event {
	private $hiddenFields = [];

	public function addHiddenField(string $name, string $value): void {
		$this->hiddenFields[$name] = $value;
	}

	public function getHiddenFields(): array {
		return $this->hiddenFields;
	}
}
