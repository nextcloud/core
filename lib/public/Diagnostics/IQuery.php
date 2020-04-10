<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr Mrówczyński <mrow4a@yahoo.com>
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

namespace OCP\Diagnostics;

/**
 * Interface IQuery
 *
 * @package OCP\Diagnostics
 * @since 8.0.0
 */
interface IQuery {
	/**
	 * @return string
	 * @since 8.0.0
	 */
	public function getSql();

	/**
	 * @return array
	 * @since 8.0.0
	 */
	public function getParams();

	/**
	 * @return float
	 * @since 8.0.0
	 */
	public function getDuration();

	/**
	 * @return float
	 * @since 11.0.0
	 */
	public function getStartTime();

	/**
	 * @return array
	 * @since 11.0.0
	 */
	public function getStacktrace();
	/**
	 * @return array
	 * @since 12.0.0
	 */
	public function getStart();
}
