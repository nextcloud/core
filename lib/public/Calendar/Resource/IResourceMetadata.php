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

namespace OCP\Calendar\Resource;

/**
 * Interface IResourceMetadata
 *
 * This interface provides keys for common metadata.
 * Resource Backends are not limited to this list and can provide
 * any metadata they want.
 *
 * @package OCP\Calendar\Resource
 * @since 17.0.0
 */
interface IResourceMetadata {

	/**
	 * Type of resource
	 *
	 * Allowed values for this key include:
	 * - projector
	 * - tv
	 * - vehicle
	 * - other
	 *
	 * @since 17.0.0
	 */
	public const RESOURCE_TYPE = '{http://nextcloud.com/ns}resource-type';

	/**
	 * If resource is of type vehicle, this describes the type of vehicle
	 *
	 * Allowed values:
	 * - bicycle
	 * - scooter
	 * - motorbike
	 * - car
	 * - plane
	 * - helicopter
	 * - other
	 *
	 * @since 17.0.0
	 */
	public const VEHICLE_TYPE = '{http://nextcloud.com/ns}resource-vehicle-type';

	/**
	 * Make of the vehicle
	 *
	 * @since 17.0.0
	 */
	public const VEHICLE_MAKE = '{http://nextcloud.com/ns}resource-vehicle-make';

	/**
	 * Model of the vehicle
	 *
	 * @since 17.0.0
	 */
	public const VEHICLE_MODEL = '{http://nextcloud.com/ns}resource-vehicle-model';

	/**
	 * Whether or not the car is electric
	 *
	 * use '1' for electric, '0' for non-electric
	 *
	 * @since 17.0.0
	 */
	public const VEHICLE_IS_ELECTRIC = '{http://nextcloud.com/ns}resource-vehicle-is-electric';

	/**
	 * Range of vehicle with a full tank
	 *
	 * @since 17.0.0
	 */
	public const VEHICLE_RANGE = '{http://nextcloud.com/ns}resource-vehicle-range';

	/**
	 * Seating capacity of the vehicle
	 *
	 * @since 17.0.0
	 */
	public const VEHICLE_SEATING_CAPACITY = '{http://nextcloud.com/ns}resource-vehicle-seating-capacity';

	/**
	 * Contact information about the person who is responsible to administer / maintain this resource
	 * This key stores a textual description of name and possible ways to contact the person
	 *
	 * @since 17.0.0
	 */
	public const CONTACT_PERSON = '{http://nextcloud.com/ns}resource-contact-person';

	/**
	 * Link to the vcard of the contact person
	 *
	 * @since 17.0.0
	 */
	public const CONTACT_PERSON_VCARD = '{http://nextcloud.com/ns}resource-contact-person-vcard';
}
