<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCP\AppFramework\Http\Template;

use OCP\Util;

/**
 * Class SimpleMenuAction
 *
 * @since 14.0.0
 */
class SimpleMenuAction implements IMenuAction {

	/** @var string */
	private $id;

	/** @var string */
	private $label;

	/** @var string */
	private $icon;

	/** @var string */
	private $link;

	/** @var int */
	private $priority;

	/** @var string */
	private $detail;

	/**
	 * SimpleMenuAction constructor.
	 *
	 * @param string $id
	 * @param string $label
	 * @param string $icon
	 * @param string $link
	 * @param int $priority
	 * @param string $detail
	 * @since 14.0.0
	 */
	public function __construct(string $id, string $label, string $icon, string $link = '', int $priority = 100, string $detail = '') {
		$this->id = $id;
		$this->label = $label;
		$this->icon = $icon;
		$this->link = $link;
		$this->priority = $priority;
		$this->detail = $detail;
	}

	/**
	 * @return string
	 * @since 14.0.0
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @return string
	 * @since 14.0.0
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * @return string
	 * @since 14.0.0
	 */
	public function getIcon(): string {
		return $this->icon;
	}

	/**
	 * @return string
	 * @since 14.0.0
	 */
	public function getLink(): string {
		return $this->link;
	}

	/**
	 * @return int
	 * @since 14.0.0
	 */
	public function getPriority(): int {
		return $this->priority;
	}

	/**
	 * @return string
	 * @since 14.0.0
	 */
	public function render(): string {
		$detailContent = ($this->detail !== '') ? '&nbsp;<span class="download-size">(' . Util::sanitizeHTML($this->detail) . ')</span>' : '';
		return sprintf(
			'<li id="%s"><a href="%s"><span class="icon %s"></span>%s %s</a></li>',
			Util::sanitizeHTML($this->id), Util::sanitizeHTML($this->link), Util::sanitizeHTML($this->icon), Util::sanitizeHTML($this->label), $detailContent
		);
	}
}
