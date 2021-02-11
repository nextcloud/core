<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Tanghus <thomas@tanghus.net>
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

/**
 * This file defines common constants used in ownCloud
 */

namespace OCP;

/**
 * Class Constants
 *
 * @since 8.0.0
 */
class Constants {
	/**
	 * CRUDS permissions.
	 * @since 8.0.0
	 */
	public const PERMISSION_CREATE = 4;
	public const PERMISSION_READ = 1;
	public const PERMISSION_UPDATE = 2;
	public const PERMISSION_DELETE = 8;
	public const PERMISSION_SHARE = 16;
	public const PERMISSION_ALL = 31;

	/**
	 * @since 8.0.0 - Updated in 9.0.0 to allow all POSIX chars since we no
	 * longer support windows as server platform.
	 */
	public const FILENAME_INVALID_CHARS = "\\/";

	/**
	 * @since 21.0.0 – default value for autocomplete/search results limit,
	 * cf. sharing.maxAutocompleteResults in config.sample.php.
	 */
	public const SHARING_MAX_AUTOCOMPLETE_RESULTS_DEFAULT = 25;
}
