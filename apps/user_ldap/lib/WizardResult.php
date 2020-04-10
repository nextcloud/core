<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\User_LDAP;

class WizardResult {
	protected $changes = [];
	protected $options = [];
	protected $markedChange = false;

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function addChange($key, $value) {
		$this->changes[$key] = $value;
	}

	
	public function markChange() {
		$this->markedChange = true;
	}

	/**
	 * @param string $key
	 * @param array|string $values
	 */
	public function addOptions($key, $values) {
		if(!is_array($values)) {
			$values = [$values];
		}
		$this->options[$key] = $values;
	}

	/**
	 * @return bool
	 */
	public function hasChanges() {
		return (count($this->changes) > 0 || $this->markedChange);
	}

	/**
	 * @return array
	 */
	public function getResultArray() {
		$result = [];
		$result['changes'] = $this->changes;
		if(count($this->options) > 0) {
			$result['options'] = $this->options;
		}
		return $result;
	}
}
