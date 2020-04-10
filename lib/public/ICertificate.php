<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCP;

/**
 * Interface ICertificate
 *
 * @package OCP
 * @since 8.0.0
 */
interface ICertificate {
	/**
	 * @return string
	 * @since 8.0.0
	 */
	public function getName();

	/**
	 * @return string
	 * @since 8.0.0
	 */
	public function getCommonName();

	/**
	 * @return string
	 * @since 8.0.0
	 */
	public function getOrganization();

	/**
	 * @return \DateTime
	 * @since 8.0.0
	 */
	public function getIssueDate();

	/**
	 * @return \DateTime
	 * @since 8.0.0
	 */
	public function getExpireDate();

	/**
	 * @return bool
	 * @since 8.0.0
	 */
	public function isExpired();

	/**
	 * @return string
	 * @since 8.0.0
	 */
	public function getIssuerName();

	/**
	 * @return string
	 * @since 8.0.0
	 */
	public function getIssuerOrganization();
}
