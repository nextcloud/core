<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCP\Share;

use OC\Share20\Exception\ProviderException;

/**
 * Interface IProviderFactory
 *
 * @package OC\Share20
 * @since 9.0.0
 */
interface IProviderFactory {

	/**
	 * @param string $id
	 * @return IShareProvider
	 * @throws ProviderException
	 * @since 9.0.0
	 */
	public function getProvider($id);

	/**
	 * @param int $shareType
	 * @return IShareProvider
	 * @throws ProviderException
	 * @since 9.0.0
	 */
	public function getProviderForType($shareType);

	/**
	 * @return IShareProvider[]
	 * @since 11.0.0
	 */
	public function getAllProviders();
}
