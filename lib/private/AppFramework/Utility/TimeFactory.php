<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\AppFramework\Utility;

use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Needed to mock calls to time()
 */
class TimeFactory implements ITimeFactory {


	/**
	 * @return int the result of a call to time()
	 */
	public function getTime(): int {
		return time();
	}

	/**
	 * @param string $time
	 * @param \DateTimeZone $timezone
	 * @return \DateTime
	 * @since 15.0.0
	 */
	public function getDateTime(string $time = 'now', \DateTimeZone $timezone = null): \DateTime {
		return new \DateTime($time, $timezone);
	}

}
