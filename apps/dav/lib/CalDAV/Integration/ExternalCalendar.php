<?php
/**
 * @copyright 2020, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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
namespace OCA\DAV\CalDAV\Integration;

use Sabre\CalDAV;
use Sabre\DAV;

/**
 * Class ExternalCalendar
 *
 * @package OCA\DAV\CalDAV\Integration
 * @since 19.0.0
 */
abstract class ExternalCalendar implements CalDAV\ICalendar, DAV\IProperties {

	/** @var string */
	private const PREFIX = 'app-generated';

	/**
	 * @var string
	 *
	 * Double dash is a valid delimiter,
	 * because it will always split the calendarURIs correctly:
	 * - our prefix contains only one dash and won't be split
	 * - appIds are not allowed to contain dashes as per spec:
	 * > must contain only lowercase ASCII characters and underscore
	 * - explode has a limit of three, so even if the app-generated
	 *   calendar uri has double dashes, it won't be split
	 */
	private const DELIMITER = '--';

	/** @var string */
	private $appId;

	/** @var string */
	private $calendarUri;

	/**
	 * ExternalCalendar constructor.
	 *
	 * @param string $appId
	 * @param string $calendarUri
	 */
	public function __construct(string $appId, string $calendarUri) {
		$this->appId = $appId;
		$this->calendarUri = $calendarUri;
	}

	/**
	 * @inheritDoc
	 */
	final public function getName() {
		return implode(self::DELIMITER, [
			self::PREFIX,
			$this->appId,
			$this->calendarUri,
		]);
	}

	/**
	 * @inheritDoc
	 */
	final public function setName($name) {
		throw new DAV\Exception\MethodNotAllowed('Renaming calendars is not yet supported');
	}

	/**
	 * @inheritDoc
	 */
	final public function createDirectory($name) {
		throw new DAV\Exception\MethodNotAllowed('Creating collections in calendar objects is not allowed');

	}

	/**
	 * Checks whether the calendar uri is app-generated
	 *
	 * @param string $calendarUri
	 * @return bool
	 */
	public static function isAppGeneratedCalendar(string $calendarUri):bool {
		return strpos($calendarUri, self::PREFIX) === 0 && substr_count($calendarUri, self::DELIMITER) >= 2;
	}

	/**
	 * Splits an app-generated calendar-uri into appId and calendarUri
	 *
	 * @param string $calendarUri
	 * @return array
	 */
	public static function splitAppGeneratedCalendarUri(string $calendarUri):array {
		$array = array_slice(explode(self::DELIMITER, $calendarUri, 3), 1);
		// Check the array has expected amount of elements
		// and none of them is an empty string
		if (\count($array) !== 2 || \in_array('', $array, true)) {
			throw new \InvalidArgumentException('Provided calendar uri was not app-generated');
		}

		return $array;
	}

	/**
	 * Checks whether a calendar-name, the user wants to create, violates
	 * the reserved name for calendar uris
	 *
	 * @param string $calendarUri
	 * @return bool
	 */
	public static function doesViolateReservedName(string $calendarUri):bool {
		return strpos($calendarUri, self::PREFIX) === 0;
	}
}
