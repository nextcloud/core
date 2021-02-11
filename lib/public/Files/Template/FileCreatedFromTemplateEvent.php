<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCP\Files\Template;

use OCP\EventDispatcher\Event;
use OCP\Files\File;

/**
 * @since 21.0.0
 */
class FileCreatedFromTemplateEvent extends Event {
	private $template;
	private $target;

	/**
	 * @param File|null $template
	 * @param File $target
	 * @since 21.0.0
	 */
	public function __construct(?File $template, File $target) {
		$this->template = $template;
		$this->target = $target;
	}

	/**
	 * @return File|null
	 * @since 21.0.0
	 */
	public function getTemplate(): ?File {
		return $this->template;
	}

	/**
	 * @return File
	 * @since 21.0.0
	 */
	public function getTarget(): File {
		return $this->target;
	}
}
