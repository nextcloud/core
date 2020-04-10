<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
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
// Show warning if a PHP version below 7.2 is used,
if (PHP_VERSION_ID < 70200) {
	http_response_code(500);
	echo 'This version of Nextcloud requires at least PHP 7.2<br/>';
	echo 'You are currently running ' . PHP_VERSION . '. Please update your PHP version.';
	exit(-1);
}

// Show warning if > PHP 7.4 is used as Nextcloud is not compatible with > PHP 7.4 for now
if (PHP_VERSION_ID >= 70500) {
	http_response_code(500);
	echo 'This version of Nextcloud is not compatible with > PHP 7.4.<br/>';
	echo 'You are currently running ' . PHP_VERSION . '.';
	exit(-1);
}
