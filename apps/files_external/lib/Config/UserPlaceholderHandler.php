<?php
/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\Files_External\Config;

class UserPlaceholderHandler extends UserContext implements IConfigHandler {
	use SimpleSubstitutionTrait;

	/**
	 * @param mixed $optionValue
	 * @return mixed the same type as $optionValue
	 * @since 16.0.0
	 */
	public function handle($optionValue) {
		$this->placeholder = 'user';
		$uid = $this->getUserId();
		if($uid === null) {
			return $optionValue;
		}
		return $this->processInput($optionValue, $uid);
	}
}
