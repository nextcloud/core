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

namespace OCP\Calendar\Room;

/**
 * Interface IRoomMetadata
 *
 * This interface provides keys for common metadata.
 * Room Backends are not limited to this list and can provide
 * any metadata they want.
 *
 * @package OCP\Calendar\Room
 * @since 17.0.0
 */
interface IRoomMetadata {

	/**
	 * Type of room
	 *
	 * Allowed values for this key include:
	 * - meeting-room
	 * - lecture-hall
	 * - seminar-room
	 * - other
	 *
	 * @since 17.0.0
	 */
	public const ROOM_TYPE = '{http://nextcloud.com/ns}room-type';

	/**
	 * Seating capacity of the room
	 *
	 * @since 17.0.0
	 */
	public const CAPACITY = '{http://nextcloud.com/ns}room-seating-capacity';

	/**
	 * The physical address of the building this room is located in
	 *
	 * @since 17.0.0
	 */
	public const BUILDING_ADDRESS = '{http://nextcloud.com/ns}room-building-address';

	/**
	 * The story of the building this rooms is located in
	 *
	 * @since 17.0.0
	 */
	public const BUILDING_STORY = '{http://nextcloud.com/ns}room-building-story';

	/**
	 * The room-number
	 *
	 * @since 17.0.0
	 */
	public const BUILDING_ROOM_NUMBER = '{http://nextcloud.com/ns}room-building-room-number';

	/**
	 * Features provided by the room.
	 * This is a stringified list of features.
	 * Example: "PHONE,VIDEO-CONFERENCING"
	 *
	 * Standard features include:
	 * - PHONE: This room is fitted with a phone
	 * - VIDEO-CONFERENCING: This room is fitted with a video-conferencing system
	 * - TV: This room is fitted with a TV
	 * - PROJECTOR: This room is fitted with a projector
	 * - WHITEBOARD: This room is fitted with a whiteboard
	 * - WHEELCHAIR-ACCESSIBLE: This room is wheelchair-accessible
	 *
	 * @since 17.0.0
	 */
	public const FEATURES = '{http://nextcloud.com/ns}room-features';
}
