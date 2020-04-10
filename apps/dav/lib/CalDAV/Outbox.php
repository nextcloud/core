<?php
/**
 * @copyright Copyright (c) 2018, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\CalDAV;

use OCP\IConfig;
use Sabre\CalDAV\Plugin as CalDAVPlugin;

/**
 * Class Outbox
 *
 * @package OCA\DAV\CalDAV
 */
class Outbox extends \Sabre\CalDAV\Schedule\Outbox {

	/** @var IConfig */
	private $config;

	/** @var null|bool */
	private $disableFreeBusy = null;

	/**
	 * Outbox constructor.
	 *
	 * @param IConfig $config
	 * @param string $principalUri
	 */
	public function __construct(IConfig $config, string $principalUri) {
		parent::__construct($principalUri);
		$this->config = $config;
	}

	/**
	 * Returns a list of ACE's for this node.
	 *
	 * Each ACE has the following properties:
	 *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
	 *     currently the only supported privileges
	 *   * 'principal', a url to the principal who owns the node
	 *   * 'protected' (optional), indicating that this ACE is not allowed to
	 *      be updated.
	 *
	 * @return array
	 */
	function getACL() {
		// getACL is called so frequently that we cache the config result
		if ($this->disableFreeBusy === null) {
			$this->disableFreeBusy = ($this->config->getAppValue('dav', 'disableFreeBusy', 'no') === 'yes');
		}

		$commonAcl = [
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-read',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-write',
				'protected' => true,
			],
		];

		// schedule-send is an aggregate privilege for:
		// - schedule-send-invite
		// - schedule-send-reply
		// - schedule-send-freebusy
		//
		// If FreeBusy is disabled, we have to remove the latter privilege

		if ($this->disableFreeBusy) {
			return array_merge($commonAcl, [
				[
					'privilege' => '{' . CalDAVPlugin::NS_CALDAV . '}schedule-send-invite',
					'principal' => $this->getOwner(),
					'protected' => true,
				],
				[
					'privilege' => '{' . CalDAVPlugin::NS_CALDAV . '}schedule-send-invite',
					'principal' => $this->getOwner() . '/calendar-proxy-write',
					'protected' => true,
				],
				[
					'privilege' => '{' . CalDAVPlugin::NS_CALDAV . '}schedule-send-reply',
					'principal' => $this->getOwner(),
					'protected' => true,
				],
				[
					'privilege' => '{' . CalDAVPlugin::NS_CALDAV . '}schedule-send-reply',
					'principal' => $this->getOwner() . '/calendar-proxy-write',
					'protected' => true,
				],
			]);
		}

		return array_merge($commonAcl, [
			[
				'privilege' => '{' . CalDAVPlugin::NS_CALDAV . '}schedule-send',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
			[
				'privilege' => '{' . CalDAVPlugin::NS_CALDAV . '}schedule-send',
				'principal' => $this->getOwner() . '/calendar-proxy-write',
				'protected' => true,
			],
		]);
	}
}
