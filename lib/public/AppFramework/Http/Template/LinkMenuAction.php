<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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
 * Class LinkMenuAction
 *
 * @package OCP\AppFramework\Http\Template
 * @since 14.0.0
 */
class LinkMenuAction extends SimpleMenuAction {

	/**
	 * LinkMenuAction constructor.
	 *
	 * @param string $label
	 * @param string $icon
	 * @param string $link
	 * @since 14.0.0
	 */
	public function __construct(string $label, string $icon, string $link) {
		parent::__construct('directLink-container', $label, $icon, $link);
	}

	/**
	 * @return string
	 * @since 14.0.0
	 */
	public function render(): string {
		return '<li>' .
			'<a id="directLink-container">' .
			'<span class="icon ' . Util::sanitizeHTML($this->getIcon()) . '"></span>' .
			'<label for="directLink">' . Util::sanitizeHTML($this->getLabel()) . '</label>' .
			'</a>' .
			'</li>' .
			'<li>' .
			'<span class="menuitem">' .
			'<input id="directLink" type="text" readonly="" value="' . Util::sanitizeHTML($this->getLink()) . '">' .
			'</span>' .
			'</li>';
	}
}
