<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCP\User\Backend;

/**
 * @since 17.0.0
 * @deprecated 21.0.0
 */
interface IGetRealUIDBackend {

	/**
	 * Some backends accept different UIDs than what is the internal UID to be used.
	 * For example the database backend accepts different cased UIDs in all the functions
	 * but the internal UID that is to be used should be correctly cased.
	 *
	 * This little function makes sure that the used UID will be correct hen using the user object
	 *
	 * @since 17.0.0
	 * @deprecated 21.0.0
	 * @param string $uid
	 * @return string
	 */
	public function getRealUID(string $uid): string;
}
