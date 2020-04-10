<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\CalDAV;

use OCP\IConfig;
use OCP\IL10N;
use Sabre\DAV\Collection;

class PublicCalendarRoot extends Collection {

	/** @var CalDavBackend */
	protected $caldavBackend;

	/** @var \OCP\IL10N */
	protected $l10n;

	/** @var \OCP\IConfig */
	protected $config;

	/**
	 * PublicCalendarRoot constructor.
	 *
	 * @param CalDavBackend $caldavBackend
	 * @param IL10N $l10n
	 * @param IConfig $config
	 */
	function __construct(CalDavBackend $caldavBackend, IL10N $l10n,
						 IConfig $config) {
		$this->caldavBackend = $caldavBackend;
		$this->l10n = $l10n;
		$this->config = $config;

	}

	/**
	 * @inheritdoc
	 */
	function getName() {
		return 'public-calendars';
	}

	/**
	 * @inheritdoc
	 */
	function getChild($name) {
		$calendar = $this->caldavBackend->getPublicCalendar($name);
		return new PublicCalendar($this->caldavBackend, $calendar, $this->l10n, $this->config);
	}

	/**
	 * @inheritdoc
	 */
	function getChildren() {
		return [];
	}
}
