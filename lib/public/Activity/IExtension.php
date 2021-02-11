<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCP\Activity;

/**
 * Interface IExtension
 *
 * @since 8.0.0
 */
interface IExtension {
	public const METHOD_STREAM = 'stream';
	public const METHOD_MAIL = 'email';
	public const METHOD_NOTIFICATION = 'notification';

	public const PRIORITY_VERYLOW = 10;
	public const PRIORITY_LOW = 20;
	public const PRIORITY_MEDIUM = 30;
	public const PRIORITY_HIGH = 40;
	public const PRIORITY_VERYHIGH = 50;
}
