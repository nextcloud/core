<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Georg Ehrke
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

namespace OCA\DAV\CalDAV\Reminder\NotificationProvider;

/**
 * Class AudioProvider
 *
 * This class only extends PushProvider at the moment. It does not provide true
 * audio-alarms yet, but it's better than no alarm at all right now.
 *
 * @package OCA\DAV\CalDAV\Reminder\NotificationProvider
 */
class AudioProvider extends PushProvider {

	/** @var string */
	public const NOTIFICATION_TYPE = 'AUDIO';
}
