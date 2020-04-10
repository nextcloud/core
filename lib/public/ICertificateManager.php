<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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
 * Manage trusted certificates for users
 * @since 8.0.0
 */
interface ICertificateManager {
	/**
	 * Returns all certificates trusted by the user
	 *
	 * @return \OCP\ICertificate[]
	 * @since 8.0.0
	 */
	public function listCertificates();

	/**
	 * @param string $certificate the certificate data
	 * @param string $name the filename for the certificate
	 * @return \OCP\ICertificate
	 * @throws \Exception If the certificate could not get added
	 * @since 8.0.0 - since 8.1.0 throws exception instead of returning false
	 */
	public function addCertificate($certificate, $name);

	/**
	 * @param string $name
	 * @since 8.0.0
	 */
	public function removeCertificate($name);

	/**
	 * Get the path to the certificate bundle for this user
	 *
	 * @param string $uid (optional) user to get the certificate bundle for, use `null` to get the system bundle (since 9.0.0)
	 * @return string
	 * @since 8.0.0
	 */
	public function getCertificateBundle($uid = '');

	/**
	 * Get the full local path to the certificate bundle for this user
	 *
	 * @param string $uid (optional) user to get the certificate bundle for, use `null` to get the system bundle
	 * @return string
	 * @since 9.0.0
	 */
	public function getAbsoluteBundlePath($uid = '');
}
