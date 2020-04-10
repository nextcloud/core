<?php
/**
 * @copyright 2019, Georg Ehrke <oc.list@georgehrke.com>
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
namespace OCA\DAV\CalDAV\ICSExportPlugin;

use OCP\IConfig;
use OCP\ILogger;
use Sabre\HTTP\ResponseInterface;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property\ICalendar\Duration;

/**
 * Class ICSExportPlugin
 *
 * @package OCA\DAV\CalDAV\ICSExportPlugin
 */
class ICSExportPlugin extends \Sabre\CalDAV\ICSExportPlugin {

	/** @var IConfig */
	private $config;

	/** @var ILogger */
	private $logger;

	/** @var string */
	private const DEFAULT_REFRESH_INTERVAL = 'PT4H';

	/**
	 * ICSExportPlugin constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config, ILogger $logger) {
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	protected function generateResponse($path, $start, $end, $expand, $componentType, $format, $properties, ResponseInterface $response) {
		if (!isset($properties['{http://nextcloud.com/ns}refresh-interval'])) {
			$value = $this->config->getAppValue('dav', 'defaultRefreshIntervalExportedCalendars', self::DEFAULT_REFRESH_INTERVAL);
			$properties['{http://nextcloud.com/ns}refresh-interval'] = $value;
		}

		return parent::generateResponse($path, $start, $end, $expand, $componentType, $format, $properties, $response);
	}

	/**
	 * @inheritDoc
	 */
	public function mergeObjects(array $properties, array $inputObjects) {
		$vcalendar = parent::mergeObjects($properties, $inputObjects);

		if (isset($properties['{http://nextcloud.com/ns}refresh-interval'])) {
			$refreshIntervalValue = $properties['{http://nextcloud.com/ns}refresh-interval'];
			try {
				DateTimeParser::parseDuration($refreshIntervalValue);
			} catch (InvalidDataException $ex) {
				$this->logger->debug('Invalid refresh interval for exported calendar, falling back to default value ...');
				$refreshIntervalValue = self::DEFAULT_REFRESH_INTERVAL;
			}

			// https://tools.ietf.org/html/rfc7986#section-5.7
			$refreshInterval = new Duration($vcalendar, 'REFRESH-INTERVAL', $refreshIntervalValue);
			$refreshInterval->add('VALUE', 'DURATION');
			$vcalendar->add($refreshInterval);

			// Legacy property for compatibility
			$vcalendar->{'X-PUBLISHED-TTL'} = $refreshIntervalValue;
		}

		return $vcalendar;
	}

}
