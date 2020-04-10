<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Maxence Lange <maxence@nextcloud.com>
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

namespace OC\Share;

use OCP\Share\IShare;

class Constants {

	/**
	 * @deprecated 17.0.0 - use IShare::TYPE_USER instead
	 */
	const SHARE_TYPE_USER = 0;
	/**
	 * @deprecated 17.0.0 - use IShare::TYPE_GROUP instead
	 */
	const SHARE_TYPE_GROUP = 1;
	// const SHARE_TYPE_USERGROUP = 2; // Internal type used by DefaultShareProvider
	/**
	 * @deprecated 17.0.0 - use IShare::TYPE_LINK instead
	 */
	const SHARE_TYPE_LINK = 3;
	/**
	 * @deprecated 17.0.0 - use IShare::TYPE_EMAIL instead
	 */
	const SHARE_TYPE_EMAIL = 4;
	const SHARE_TYPE_CONTACT = 5; // ToDo Check if it is still in use otherwise remove it
	/**
	 * @deprecated 17.0.0 - use IShare::TYPE_REMOTE instead
	 */
	const SHARE_TYPE_REMOTE = 6;
	/**
	 * @deprecated 17.0.0 - use IShare::TYPE_CIRCLE instead
	 */
	const SHARE_TYPE_CIRCLE = 7;
	/**
	 * @deprecated 17.0.0 - use IShare::TYPE_GUEST instead
	 */
	const SHARE_TYPE_GUEST = 8;
	/**
	 * @deprecated 17.0.0 - use IShare::REMOTE_GROUP instead
	 */
	const SHARE_TYPE_REMOTE_GROUP = 9;
	/**
	 * @deprecated 17.0.0 - use IShare::TYPE_ROOM instead
	 */
	const SHARE_TYPE_ROOM = 10;
	// const SHARE_TYPE_USERROOM = 11; // Internal type used by RoomShareProvider

	const FORMAT_NONE = -1;
	const FORMAT_STATUSES = -2;
	const FORMAT_SOURCES = -3;  // ToDo Check if it is still in use otherwise remove it

	const RESPONSE_FORMAT = 'json'; // default resonse format for ocs calls

	const TOKEN_LENGTH = 15; // old (oc7) length is 32, keep token length in db at least that for compatibility

	protected static $shareTypeUserAndGroups = -1;
	protected static $shareTypeGroupUserUnique = 2;
	protected static $backends = [];
	protected static $backendTypes = [];
	protected static $isResharingAllowed;
}
