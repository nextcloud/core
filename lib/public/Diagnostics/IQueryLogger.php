<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr Mrówczyński <mrow4a@yahoo.com>
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

namespace OCP\Diagnostics;

use Doctrine\DBAL\Logging\SQLLogger;

/**
 * Interface IQueryLogger
 *
 * @since 8.0.0
 */
interface IQueryLogger extends SQLLogger {
	/**
	 * Mark the start of a query providing query SQL statement, its parameters and types.
	 * This method should be called as close to the DB as possible and after
	 * query is finished finalized with stopQuery() method.
	 *
	 * @param string $sql
	 * @param array|null $params
	 * @param array|null $types
	 * @since 8.0.0
	 */
	public function startQuery($sql, array $params = null, array $types = null);

	/**
	 * Mark the end of the current active query. Ending query should store \OCP\Diagnostics\IQuery to
	 * be returned with getQueries() method.
	 *
	 * @return mixed
	 * @since 8.0.0
	 */
	public function stopQuery();

	/**
	 * This method should return all \OCP\Diagnostics\IQuery objects stored using
	 * startQuery()/stopQuery() methods.
	 *
	 * @return \OCP\Diagnostics\IQuery[]
	 * @since 8.0.0
	 */
	public function getQueries();

	/**
	 * Activate the module for the duration of the request. Deactivated module
	 * does not create and store \OCP\Diagnostics\IQuery objects.
	 * Only activated module should create and store objects to be
	 * returned with getQueries() call.
	 *
	 * @since 12.0.0
	 */
	public function activate();
}
