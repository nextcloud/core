<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author AW-UC <git@a-wesemann.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC;

class NaturalSort_DefaultCollator {
	public function compare($a, $b) {
		$result = strcasecmp($a, $b);
		if ($result === 0) {
			if ($a === $b) {
				return 0;
			}
			return ($a > $b) ? -1 : 1;
		}
		return ($result < 0) ? -1 : 1;
	}
}
